<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\AuthContext;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Model\Database;
use App\Service\AthleteDuplicateCleanupResult;
use App\Service\AthleteDuplicateReconciler;
use Throwable;

final class AthleteMaintenanceController extends Controller
{
    public const CONFIRMATION = 'APPLY ATHLETE CLEANUP';

    private const PREVIEW_SESSION_KEY = 'athlete_duplicate_cleanup_preview';
    private const PREVIEW_MAX_AGE_SECONDS = 1_800;

    private ?AthleteDuplicateReconciler $reconciler;

    public function __construct(
        View $view,
        Request $request,
        ?AthleteDuplicateReconciler $reconciler = null,
        ?Logger $logger = null
    ) {
        parent::__construct($view, $request, $logger);
        $this->reconciler = $reconciler;
    }

    public function duplicates(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        if (!self::enabled()) {
            throw new HttpException(404, __('errors.page_not_found'));
        }

        $clubs = $this->clubs();
        $errors = [];
        $result = null;
        $selectedClubId = '';
        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            $selectedClubId = trim((string) $request->post('club_id'));
            $clubId = $this->selectedClubId($selectedClubId, $clubs, $errors);
            $operation = (string) $request->post('operation');

            if (!in_array($operation, ['preview', 'apply'], true)) {
                $errors[] = __('admin.athlete_cleanup.errors.invalid_operation');
            }

            if ($operation === 'apply') {
                $this->validateApplyRequest($request, $clubId, $errors);
            }

            if ($errors === []) {
                try {
                    $apply = $operation === 'apply';
                    $result = $this->reconciler()->run($apply, $clubId);
                    if ($apply) {
                        Session::delete(self::PREVIEW_SESSION_KEY);
                    } else {
                        $this->grantApplyAfterPreview($clubId);
                    }
                } catch (Throwable $exception) {
                    $this->reportFailure('admin.athlete_duplicate_cleanup_failed', $exception, $request);
                    $errors[] = __('admin.athlete_cleanup.errors.failed', [
                        'reference' => $request->correlationId(),
                    ]);
                }
            }
        }

        return $this->maintenanceView($clubs, $selectedClubId, $result, $errors);
    }

    public static function enabled(): bool
    {
        return filter_var(
            env('ATHLETE_DUPLICATE_MAINTENANCE', false),
            FILTER_VALIDATE_BOOL
        ) === true;
    }

    /**
     * @return list<array{id: int, federal_code: string, name: string}>
     */
    private function clubs(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, federal_code, name FROM clubs ORDER BY name ASC, id ASC'
        );
        $clubs = [];
        foreach ($statement->fetchAll() ?: [] as $club) {
            $clubs[] = [
                'id' => (int) $club['id'],
                'federal_code' => (string) $club['federal_code'],
                'name' => (string) $club['name'],
            ];
        }

        return $clubs;
    }

    /**
     * @param list<array{id: int, federal_code: string, name: string}> $clubs
     * @param list<string> $errors
     */
    private function selectedClubId(string $value, array $clubs, array &$errors): ?int
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('/\A[1-9]\d*\z/', $value) !== 1) {
            $errors[] = __('admin.athlete_cleanup.errors.invalid_club');

            return null;
        }

        $clubId = (int) $value;
        if (!in_array($clubId, array_column($clubs, 'id'), true)) {
            $errors[] = __('admin.athlete_cleanup.errors.invalid_club');

            return null;
        }

        return $clubId;
    }

    /** @param list<string> $errors */
    private function validateApplyRequest(Request $request, ?int $clubId, array &$errors): void
    {
        if ($request->post('backup_confirmed') !== '1') {
            $errors[] = __('admin.athlete_cleanup.errors.backup_required');
        }

        $confirmation = (string) $request->post('confirmation');
        if (!hash_equals(self::CONFIRMATION, $confirmation)) {
            $errors[] = __('admin.athlete_cleanup.errors.confirmation');
        }

        $preview = Session::get(self::PREVIEW_SESSION_KEY);
        $previewClubId = is_array($preview) && isset($preview['club_id']) && is_int($preview['club_id'])
            ? $preview['club_id']
            : null;
        $previewedAt = is_array($preview) && isset($preview['previewed_at']) && is_int($preview['previewed_at'])
            ? $preview['previewed_at']
            : null;
        if (
            $previewClubId !== ($clubId ?? 0)
            || $previewedAt === null
            || time() - $previewedAt > self::PREVIEW_MAX_AGE_SECONDS
        ) {
            $errors[] = __('admin.athlete_cleanup.errors.preview_required');
        }
    }

    private function grantApplyAfterPreview(?int $clubId): void
    {
        Session::set(self::PREVIEW_SESSION_KEY, [
            'club_id' => $clubId ?? 0,
            'previewed_at' => time(),
        ]);
    }

    private function reconciler(): AthleteDuplicateReconciler
    {
        return $this->reconciler ??= new AthleteDuplicateReconciler(Database::connection());
    }

    /**
     * @param list<array{id: int, federal_code: string, name: string}> $clubs
     * @param list<string> $errors
     */
    private function maintenanceView(
        array $clubs,
        string $selectedClubId,
        ?AthleteDuplicateCleanupResult $result,
        array $errors
    ): Response {
        return $this->view('admin/athlete_duplicate_maintenance', [
            'title' => __('admin.athlete_cleanup.title'),
            'clubs' => $clubs,
            'selectedClubId' => $selectedClubId,
            'result' => $result,
            'errors' => $errors,
            'confirmationPhrase' => self::CONFIRMATION,
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
