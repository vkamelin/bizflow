<?php declare(strict_types=1);

namespace App\Database\Migration;

use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;

final class M260412113841CreateUsersTable implements RevertibleMigrationInterface
{
    /**
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function up(MigrationBuilder $b): void
    {
        $cb = $b->columnBuilder();

        $b->createTable('users', [
            'id' => $cb::uuidPrimaryKey()->comment('Идентификатор пользователя'),
            'email' => $cb::string(320)->notNull()->unique()->comment('Email пользователя'),
            'password_hash' => $cb::string()->notNull()->comment('Хеш пароля'),
            'first_name' => $cb::string(100)->notNull()->comment('Имя пользователя'),
            'last_name' => $cb::string(100)->comment('Фамилия пользователя'),
            'phone' => $cb::string(11)->comment('Телефон пользователя'),
            'status' => $cb::tinyint()->notNull()->defaultValue(1)->comment('Статус пользователя'),
            'created_at' => $cb::datetime()->notNull(),
            'updated_at' => $cb::datetime()->notNull(),
            'deleted_at' => $cb::datetime()->notNull(),
        ]);

        $b->createTable('auth_tokens', [
            'token' => $cb::string(128)->notNull()->primaryKey(),
            'user_id' => $cb::uuid()->notNull(),
            'expires_at' => $cb::dateTime()->notNull(),
            'revoked_at' => $cb::dateTime()->null(),
            'created_at' => $cb::dateTime()->notNull(),
        ]);

        $b->createIndex('users', 'idx_email_users', 'email');

        // Индексы для фильтрации и сортировки
        $b->createIndex('users', 'idx_deleted_at_users', 'deleted_at');
        $b->createIndex('users', 'idx_created_at_users', 'created_at');
        $b->createIndex('users', 'idx_status_users', 'status');

        $b->addForeignKey('auth_tokens', 'auth_tokens_user_id_fk', 'user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $b->createIndex('auth_tokens', 'idx_auth_tokens_user_active', ['user_id', 'revoked_at']);

        $b->addCommentOnTable('users', 'Пользователи системы BizFlow');
        $b->addCommentOnTable('auth_tokens', 'Токены авторизации пользователей');
    }

    /**
     * @throws InvalidConfigException
     * @throws NotSupportedException
     */
    public function down(MigrationBuilder $b): void
    {
        $b->dropTable('users');
    }
}
