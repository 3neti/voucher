<?php

use Illuminate\Support\ServiceProvider;

it('has publishable config files', function () {
    $publishes = ServiceProvider::$publishes;
    $allPublished = collect($publishes)->flatten()->toArray();
    expect($allPublished)->not->toBeEmpty();
});
