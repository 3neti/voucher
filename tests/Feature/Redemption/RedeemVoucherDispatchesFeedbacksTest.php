<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('dispatches feedbacks after successful redemption', function () {
    $this->markTestSkipped('Feedback dispatch is not yet surfaced as a stable event/action contract in the package test API.');
});

it('does not dispatch success feedbacks after failed redemption', function () {
    $this->markTestSkipped('Feedback dispatch assertions should be enabled once feedback channels or events are explicitly modeled.');
});
