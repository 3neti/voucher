<?php

it('has database migrations directory', function () {
    expect(is_dir(__DIR__.'/../../../database/migrations'))->toBeTrue();
})->skip();

it('has test migrations for standalone testing', function () {
    expect(is_dir(__DIR__.'/../../../database/test-migrations'))->toBeTrue();
})->skip();
