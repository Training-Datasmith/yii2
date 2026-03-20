<?php

declare(strict_types=1);

/**
 * Yii 2 — ActiveRecord CRUD example.
 *
 * Demonstrates find, save, update and delete using Yii's ActiveRecord ORM
 * without the full web application stack (uses a console-style bootstrap).
 *
 * Prerequisites:
 *   composer require yiisoft/yii2
 *
 * Setup:
 *   CREATE TABLE posts (
 *       id        INT AUTO_INCREMENT PRIMARY KEY,
 *       title     VARCHAR(255) NOT NULL,
 *       body      TEXT,
 *       status    VARCHAR(20) NOT NULL DEFAULT 'draft',
 *       author_id INT NOT NULL,
 *       created_at INT
 *   );
 *
 * Run:
 *   php examples/01_active_record_crud.php
 */

defined('YII_DEBUG') || define('YII_DEBUG', true);
defined('YII_ENV')   || define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

// Minimal console application for standalone use.
new yii\console\Application([
    'id'         => 'example-app',
    'basePath'   => __DIR__ . '/..',
    'components' => [
        'db' => [
            'class'    => yii\db\Connection::class,
            'dsn'      => 'mysql:host=127.0.0.1;dbname=yii2_example',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
        ],
    ],
]);

// --- ActiveRecord model definition ------------------------------------------

/**
 * Represents a blog post stored in the `posts` table.
 *
 * @property int    $id
 * @property string $title
 * @property string $body
 * @property string $status
 * @property int    $author_id
 * @property int    $created_at Unix timestamp set automatically before insert.
 */
class Post extends yii\db\ActiveRecord
{
    public static function tableName(): string
    {
        return 'posts';
    }

    public function rules(): array
    {
        return [
            [['title', 'author_id'], 'required'],
            ['title', 'string', 'max' => 255],
            ['status', 'in', 'range' => ['draft', 'published', 'archived']],
            ['body', 'string'],
        ];
    }

    /** Set created_at timestamp before inserting. */
    public function beforeSave(bool $insert): bool
    {
        if ($insert) {
            $this->created_at = time();
        }
        return parent::beforeSave($insert);
    }
}

// --- CREATE -----------------------------------------------------------------

$post            = new Post();
$post->title     = 'Hello Yii 2 ActiveRecord';
$post->body      = 'This is a standalone example.';
$post->status    = 'draft';
$post->author_id = 1;

if ($post->save()) {
    echo "Saved post #{$post->id}\n";
} else {
    echo "Validation errors:\n";
    print_r($post->getErrors());
    exit(1);
}

// --- READ -------------------------------------------------------------------

/** @var Post $found */
$found = Post::findOne($post->id);
echo "Title: {$found->title}, Status: {$found->status}\n";

// Find multiple with conditions.
$published = Post::find()
    ->where(['status' => 'published'])
    ->orderBy(['created_at' => SORT_DESC])
    ->limit(5)
    ->all();

echo 'Published posts: ' . count($published) . "\n";

// --- UPDATE -----------------------------------------------------------------

$found->status = 'published';
$found->save();
echo "Updated status: {$found->status}\n";

// Bulk update without loading records.
Post::updateAll(['status' => 'archived'], ['<', 'created_at', strtotime('-1 year')]);
echo "Archived old posts.\n";

// --- DELETE -----------------------------------------------------------------

$found->delete();
echo "Deleted post #{$found->id}.\n";

// Bulk delete.
Post::deleteAll(['status' => 'archived']);
echo "Deleted all archived posts.\n";
