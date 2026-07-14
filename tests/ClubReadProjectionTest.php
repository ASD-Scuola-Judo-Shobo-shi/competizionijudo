<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Club;
use App\Model\Database;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
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
            'recovery_email' => 'recovery@example.test',
            'password_hash' => 'hash',
        ], 'club@example.test');
        $database = $this->databaseFor(
            $statement,
            'SELECT id, email, recovery_email, password_hash FROM clubs WHERE normalized_email = ?'
        );
        $this->databaseConnection->setValue(null, $database);

        $club = Club::findByEmail(' Club@Example.Test ');

        self::assertSame(7, $club?->id);
        self::assertSame('hash', $club?->password_hash);
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
        self::assertSame('', $club?->recovery_email);
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
