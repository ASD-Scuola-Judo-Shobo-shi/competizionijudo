<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\AuthContext;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Model\Database;
use App\Service\DataNormalization;
use RuntimeException;
use Throwable;

final class DataNormalizationController extends Controller
{
    private const PREVIEW_SESSION = 'admin_normalization_preview';

    public function review(Request $request): Response
    {
        Session::start();
        if (!AuthContext::isAdministrator()) {
            return $this->redirect('/admin/login');
        }
        $type = $request->query('type', 'clubs');
        $type = in_array($type, ['clubs', 'athletes'], true) ? $type : 'clubs';
        $service = new DataNormalization(Database::connection());
        $error = '';
        if ($request->method() === 'POST') {
            validate_csrf((string) $request->post('csrf_token'));
            $preview = Session::get(self::PREVIEW_SESSION);
            Session::delete(self::PREVIEW_SESSION);
            try {
                $token = $request->post('preview_token');
                if (
                    !is_array($preview) || !is_string($token)
                    || !hash_equals((string) $preview['token'], $token)
                    || $preview['type'] !== $type || (int) $preview['expires'] < time()
                ) {
                    throw new RuntimeException('normalization.stale');
                }
                $selected = $request->post('selected', []);
                if (!is_array($selected) || $selected === [] || count($selected) > 50) {
                    throw new RuntimeException('normalization.select');
                }
                $expected = [];
                foreach ($selected as $id) {
                    if (!is_scalar($id) || !isset($preview['records'][(int) $id])) {
                        throw new RuntimeException('normalization.stale');
                    }
                    $expected[(int) $id] = (string) $preview['records'][(int) $id];
                }
                $updated = $service->apply($type, $expected);
                Session::flash('admin_normalization_result', $updated);
                return $this->redirect('/admin/normalization?' . http_build_query([
                    'type' => $type,
                    'page' => max(1, (int) $request->query('page', 1)),
                ]));
            } catch (Throwable $exception) {
                if (
                    $exception instanceof RuntimeException && in_array($exception->getMessage(), [
                    'normalization.stale', 'normalization.select',
                    ], true)
                ) {
                    $error = __($exception->getMessage());
                } else {
                    $this->reportFailure('admin.normalization_failed', $exception, $request);
                    $error = __('normalization.failed');
                }
            }
        }
        $pagination = paginate($service->count($type), (int) $request->query('page', 1), 50, 'page', ['type' => $type]);
        $records = $service->preview($type, $pagination['offset']);
        $previewToken = bin2hex(random_bytes(24));
        Session::set(self::PREVIEW_SESSION, [
            'token' => $previewToken,
            'type' => $type,
            'expires' => time() + 1800,
            'records' => array_column($records, 'fingerprint', 'id'),
        ]);
        return $this->view('admin/normalization', [
            'title' => __('normalization.title'),
            'type' => $type,
            'records' => $records,
            'pagination' => $pagination,
            'previewToken' => $previewToken,
            'error' => $error,
            'updated' => Session::pullFlash('admin_normalization_result'),
        ])->withHeaders(['Cache-Control' => 'private, no-store, max-age=0', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
