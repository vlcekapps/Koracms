<?php

declare(strict_types=1);

namespace KoraFoodCardFlashTest;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function loadFunction(string $file, string $name): void
{
    $source = (string)file_get_contents(dirname(__DIR__) . '/' . $file);
    if (preg_match('/^function ' . preg_quote($name, '/') . '\b.*?^\}/ms', $source, $match) !== 1) {
        throw new \RuntimeException('Missing production function ' . $name);
    }
    eval('namespace ' . __NAMESPACE__ . '; ' . $match[0]);
}

$checks = 0;
$same = static function (mixed $actual, mixed $expected, string $message) use (&$checks): void {
    $checks++;
    if ($actual !== $expected) {
        throw new \RuntimeException($message);
    }
};
try {
    foreach (['foodOrderFulfillmentDefinitions', 'normalizeFoodOrderFulfillmentModes'] as $function) {
        loadFunction('lib/presentation.php', $function);
    }
    foreach (['adminEditorFormFields', 'adminEditorFormFlashStore', 'adminEditorFormFlashTake'] as $function) {
        loadFunction('admin/layout.php', $function);
    }
    $_SESSION = [];
    adminEditorFormFlashStore('food', 12, ['title' => 'Český lístek', 'content' => '<p>Rozepsáno</p>', 'type' => 'beverage',
        'order_fulfillment_modes' => ['pickup', 'invalid', 'delivery', 'pickup'], 'is_current' => '1',
        'csrf_token' => 'old', 'confirm_food_delete_12' => '1', 'deleted_at' => 'injected', 'id' => '13']);
    $same(adminEditorFormFlashTake('food', 13), [], 'Wrong editor consumed draft');
    $values = adminEditorFormFlashTake('food', 12);
    $same($values['title'], 'Český lístek', 'Title lost');
    $same($values['content'], '<p>Rozepsáno</p>', 'Content lost');
    $same($values['type'], 'beverage', 'Type lost');
    $same($values['is_current'], '1', 'Ordinary checked field lost');
    foreach (['is_published', 'orders_enabled', 'order_requested_at_enabled'] as $field) {
        $same($values[$field], '0', 'Ordinary unchecked field lost');
    }
    $same($values['order_fulfillment_modes'], 'pickup,delivery', 'Choices not normalized');
    foreach (['csrf_token', 'confirm_food_delete_12', 'deleted_at', 'id'] as $field) {
        $same(array_key_exists($field, $values), false, 'Draft restored consent or protected metadata');
    }
    $same(adminEditorFormFlashTake('food', 12), [], 'Draft consumed twice');
    adminEditorFormFlashStore('food', null, ['title' => ['invalid'], 'order_fulfillment_modes' => []]);
    $same(array_key_exists('title', adminEditorFormFlashTake('food', null)), false, 'Nonscalar field stored');
    echo 'Food card flash OK (' . $checks . ' checks)' . PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Food card flash FAILED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
