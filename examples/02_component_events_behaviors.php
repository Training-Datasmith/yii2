<?php

declare(strict_types=1);

/**
 * Yii 2 — Component events and behaviors example.
 *
 * Demonstrates:
 *  1. Attaching and triggering instance-level events on a Component.
 *  2. Writing a reusable Behavior and attaching it at runtime.
 *  3. Using class-level events that fire for every instance.
 *
 * Run:
 *   php examples/02_component_events_behaviors.php
 */

defined('YII_DEBUG') || define('YII_DEBUG', true);
defined('YII_ENV')   || define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

new yii\console\Application([
    'id'       => 'example-app',
    'basePath' => __DIR__ . '/..',
]);

// ============================================================================
// 1. Instance-level events
// ============================================================================

/**
 * A simple order processing component that fires events at key points.
 */
class OrderProcessor extends yii\base\Component
{
    public const EVENT_BEFORE_PROCESS = 'beforeProcess';
    public const EVENT_AFTER_PROCESS  = 'afterProcess';

    /** @var float Total of the processed order */
    public float $total = 0.0;

    /**
     * Process the order, firing before/after events.
     *
     * @param array<string, mixed> $items Line items ['name' => price].
     */
    public function process(array $items): void
    {
        $this->trigger(self::EVENT_BEFORE_PROCESS, new yii\base\Event());

        $this->total = array_sum($items);

        $this->trigger(self::EVENT_AFTER_PROCESS, new yii\base\Event());
    }
}

$processor = new OrderProcessor();

// Attach a logging handler to the after-process event.
$processor->on(OrderProcessor::EVENT_AFTER_PROCESS, static function (yii\base\Event $event): void {
    /** @var OrderProcessor $sender */
    $sender = $event->sender;
    echo "[Event] Order processed. Total: \${$sender->total}\n";
});

$processor->process(['Widget' => 9.99, 'Gadget' => 24.99]);

// ============================================================================
// 2. Reusable Behavior — TimestampBehavior clone
// ============================================================================

/**
 * Attaches created_at / updated_at timestamp management to any Component
 * that exposes those properties.
 */
class AuditBehavior extends yii\base\Behavior
{
    public string $created_attr = 'created_at';
    public string $updated_attr = 'updated_at';

    public function events(): array
    {
        return [
            yii\db\ActiveRecord::EVENT_BEFORE_INSERT => 'setTimestamps',
            yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => 'setUpdatedAt',
        ];
    }

    public function setTimestamps(): void
    {
        $owner = $this->owner;
        $owner->{$this->created_attr} = time();
        $owner->{$this->updated_attr} = time();
    }

    public function setUpdatedAt(): void
    {
        $this->owner->{$this->updated_attr} = time();
    }
}

// Attach the behavior at runtime (no class modification needed).
$processor->attachBehavior('audit', new AuditBehavior());
echo "AuditBehavior attached: " . ($processor->getBehavior('audit') !== null ? 'yes' : 'no') . "\n";
$processor->detachBehavior('audit');

// ============================================================================
// 3. Class-level event — fires for every instance
// ============================================================================

yii\base\Event::on(
    OrderProcessor::class,
    OrderProcessor::EVENT_BEFORE_PROCESS,
    static function (yii\base\Event $event): void {
        echo "[Class event] An OrderProcessor is about to run.\n";
    }
);

$processor2 = new OrderProcessor();
$processor2->process(['Book' => 12.50]);
// Both the class-level handler (above) and any instance handler fire in order.
