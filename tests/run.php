<?php
require_once __DIR__ . '/bootstrap.php';

$testFiles = glob(__DIR__ . '/*Test.php') ?: [];
$results = [];
$failures = 0;
$testsRun = 0;

foreach ($testFiles as $testFile) {
  $before = get_declared_classes();
  require_once $testFile;
  $after = get_declared_classes();
  $classes = array_values(array_diff($after, $before));

  foreach ($classes as $class) {
    if (!is_subclass_of($class, 'TestCase')) {
      continue;
    }

    $methods = array_filter(get_class_methods($class), static function (string $method): bool {
      return str_starts_with($method, 'test');
    });

    foreach ($methods as $method) {
      $testsRun++;
      $instance = new $class();
      try {
        $instance->setUp();
        $instance->$method();
        $results[] = ['status' => 'PASS', 'name' => $class . '::' . $method];
      } catch (Throwable $e) {
        $failures++;
        $results[] = [
          'status' => 'FAIL',
          'name' => $class . '::' . $method,
          'message' => $e->getMessage(),
        ];
      }
    }
  }
}

foreach ($results as $result) {
  echo '[' . $result['status'] . '] ' . $result['name'];
  if (isset($result['message'])) {
    echo ' - ' . $result['message'];
  }
  echo PHP_EOL;
}

echo PHP_EOL . 'Tests run: ' . $testsRun . ', Failures: ' . $failures . PHP_EOL;
exit($failures > 0 ? 1 : 0);

