<?php

declare(strict_types=1);

/**
 * Yii 2 — Model validation and scenarios example.
 *
 * Demonstrates how to declare validation rules, use scenarios to activate
 * different rule subsets, perform mass assignment safely, and iterate errors.
 *
 * Run:
 *   php examples/03_model_validation.php
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
// Model definition with scenarios
// ============================================================================

/**
 * Registration / login form model.
 *
 * Scenarios:
 *   - 'register' (default): username, email, password, password_confirm required.
 *   - 'login': only username + password required.
 */
class UserForm extends yii\base\Model
{
    public const SCENARIO_LOGIN    = 'login';
    public const SCENARIO_REGISTER = self::SCENARIO_DEFAULT; // 'default'

    public ?string $username         = null;
    public ?string $email            = null;
    public ?string $password         = null;
    public ?string $password_confirm = null;

    public function scenarios(): array
    {
        return [
            self::SCENARIO_REGISTER => ['username', 'email', 'password', 'password_confirm'],
            self::SCENARIO_LOGIN    => ['username', 'password'],
        ];
    }

    public function rules(): array
    {
        return [
            // Both scenarios
            [['username', 'password'], 'required'],
            ['username', 'string', 'min' => 3, 'max' => 32],
            ['password', 'string', 'min' => 8],

            // Register only
            ['email', 'required', 'on' => self::SCENARIO_REGISTER],
            ['email', 'email',    'on' => self::SCENARIO_REGISTER],
            [
                'password_confirm',
                'compare',
                'compareAttribute' => 'password',
                'message'          => 'Passwords do not match.',
                'on'               => self::SCENARIO_REGISTER,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username'         => 'Username',
            'email'            => 'Email Address',
            'password'         => 'Password',
            'password_confirm' => 'Confirm Password',
        ];
    }
}

// ============================================================================
// 1. Registration scenario — all rules active
// ============================================================================

echo "=== Registration scenario ===\n";

$form           = new UserForm();
$form->scenario = UserForm::SCENARIO_REGISTER;
$form->setAttributes([
    'username'         => 'jo',          // too short
    'email'            => 'not-an-email',
    'password'         => 'secret',      // too short
    'password_confirm' => 'different',   // mismatch
]);

if (!$form->validate()) {
    foreach ($form->getErrors() as $attribute => $errors) {
        foreach ($errors as $error) {
            echo "  [{$form->getAttributeLabel($attribute)}] {$error}\n";
        }
    }
} else {
    echo "  Valid — would save user.\n";
}

// ============================================================================
// 2. Login scenario — only username + password rules apply
// ============================================================================

echo "\n=== Login scenario ===\n";

$login           = new UserForm();
$login->scenario = UserForm::SCENARIO_LOGIN;
$login->setAttributes([
    'username' => 'alice',
    'password' => 'supersecret',
    // email and password_confirm are ignored in this scenario
]);

if ($login->validate()) {
    echo "  Credentials are structurally valid.\n";
} else {
    print_r($login->getErrors());
}

// ============================================================================
// 3. Safe attribute filtering (mass assignment protection)
// ============================================================================

echo "\n=== Safe attributes ===\n";

$safe = new UserForm();
$safe->scenario = UserForm::SCENARIO_LOGIN;

// Only attributes declared in the scenario's safe list are set.
// Any extra key (e.g. an injected 'role') is silently ignored.
$safe->setAttributes(['username' => 'bob', 'password' => 'pass1234', 'role' => 'admin']);
echo "  Username: {$safe->username}\n";
echo "  Role (should be null — not safe): " . var_export($safe->role ?? null, true) . "\n";
