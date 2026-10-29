<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Club;
use App\Model\Database;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ClubReadProjectionTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
    }

    public function testAuthenticationLookupUsesNormalizedEmailAndMinimalCredentials(): void
    {
        $statement = $this->statementReturning([
            'id' => 7,
            'email' => 'club@example.test',
            'password_hash' => 'hash',
            'approved_at' => '2026-01-01 00:00:00',
        ], 'club@example.test');
        $database = $this->databaseFor(
            $statement,
            'SELECT id, email, password_hash, approved_at FROM clubs WHERE normalized_email = ?'
        );
        $this->databaseConnection->setValue(null, $database);

        $club = Club::findByEmail(' Club@Example.Test ');

        self::assertSame(7, $club?->id);
        self::assertSame('hash', $club?->password_hash);
        self::assertTrue($club?->isApproved());
    }

    public function testLayoutLookupDoesNotHydrateCredentialOrRecoveryFields(): void
    {
        $statement = $this->statementReturning([
            'id' => 7,
            'email' => 'club@example.test',
        ], 7);
        $database = $this->databaseFor(
            $statement,
            'SELECT id, email FROM clubs WHERE id = ?'
        );
        $this->databaseConnection->setValue(null, $database);

        $club = Club::findForLayoutById(7);

        self::assertSame('club@example.test', $club?->email);
        self::assertSame('', $club?->password_hash);
        self::assertSame('', $club?->city);
    }

    public function testPublicListProjectionExcludesContactFields(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::exactly(2))
            ->method('bindValue')
            ->willReturn(true);
        $statement->expects(self::once())->method('execute')->willReturn(true);
        $statement->expects(self::once())->method('fetchAll')->willReturn([[
            'id' => 7,
            'federal_code' => 'SYN-007',
            'name' => 'Synthetic Club',
        ]]);
        $database = $this->databaseFor(
            $statement,
            'SELECT id, federal_code, name FROM clubs ORDER BY name ASC, id ASC LIMIT ? OFFSET ?'
        );
        $this->databaseConnection->setValue(null, $database);

        $clubs = Club::page(50, 0);

        self::assertSame('Synthetic Club', $clubs[0]->name);
        self::assertSame('SYN-007', $clubs[0]->federal_code);
        self::assertSame('', $clubs[0]->contact_first_name);
        self::assertNull($clubs[0]->postal_code);
    }

    /** @return iterable<string, array{int}> */
    public static function clubCounts(): iterable
    {
        yield 'one club' => [1];
        yield 'many clubs' => [75];
    }

    #[DataProvider('clubCounts')]
    public function testNormalizedNameLookupUsesTwoQueriesAndStreamsIdentityFields(int $clubCount): void
    {
        $rows = [];
        for ($id = 1; $id < $clubCount; $id++) {
            $rows[] = ['id' => $id, 'name' => 'Other Synthetic Club ' . $id];
        }
        $rows[] = ['id' => $clubCount, 'name' => "a.s.d. JUDO CITTA'"];
        $names = $this->createMock(PDOStatement::class);
        $names->expects(self::once())->method('execute')->with()->willReturn(true);
        $names->expects(self::exactly($clubCount))->method('fetch')->willReturnOnConsecutiveCalls(...$rows);
        $names->expects(self::never())->method('fetchAll');
        $names->expects(self::once())->method('closeCursor')->willReturn(true);
        $club = $this->statementReturning([
            'id' => $clubCount, 'name' => "a.s.d. JUDO CITTA'", 'email' => 'synthetic@example.test',
        ], $clubCount);
        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(2))->method('prepare')->willReturnCallback(
            static fn(string $sql): PDOStatement => match ($sql) {
                'SELECT id, name FROM clubs ORDER BY id' => $names,
                'SELECT * FROM clubs WHERE id = ?' => $club,
            }
        );
        $this->databaseConnection->setValue(null, $database);

        $result = Club::findByName('ASD Judo Città');

        self::assertSame($clubCount, $result?->id);
        self::assertSame('synthetic@example.test', $result?->email);
    }

    public function testNormalizedNameLookupDoesNotQueryForABlankName(): void
    {
        $database = $this->createMock(PDO::class);
        $database->expects(self::never())->method('prepare');
        $this->databaseConnection->setValue(null, $database);

        self::assertNull(Club::findByName(" \t "));
    }

    /** @return PDO&MockObject */
    private function databaseFor(PDOStatement $statement, string $expectedSql): PDO
    {
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())
            ->method('prepare')
            ->with($expectedSql)
            ->willReturn($statement);

        return $database;
    }

    /** @return PDOStatement&MockObject */
    private function statementReturning(array $row, string|int $expectedParameter): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (array $values) use ($expectedParameter): bool {
                self::assertSame([$expectedParameter], $values);

                return true;
            });
        $statement->expects(self::once())->method('fetch')->willReturn($row);

        return $statement;
    }
}
