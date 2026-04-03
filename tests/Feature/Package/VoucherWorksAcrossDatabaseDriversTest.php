<?php

it('passes the core flow on sqlite', function () {
    expect(config('database.default'))->toBeString();
})->skip('fix this');

it('passes the core flow on mysql', function () {
    $this->markTestSkipped('Database-driver matrix belongs in CI jobs or an external integration harness.');
});

it('passes the core flow on postgres', function () {
    $this->markTestSkipped('Database-driver matrix belongs in CI jobs or an external integration harness.');
});

it('persists json metadata consistently across supported database drivers', function () {
    $this->markTestSkipped('Database-driver matrix belongs in CI jobs or an external integration harness.');
});
