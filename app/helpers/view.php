<?php

function view(string $template, array $data = []): void
{
  $ROOT = dirname(__DIR__); // points to /app
  extract($data, EXTR_SKIP);

  require $ROOT . "/views/" . $template . ".php";
}