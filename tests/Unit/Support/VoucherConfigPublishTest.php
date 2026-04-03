<?php

it('has publishable config files', function () {
    $publishes = \Illuminate\Support\ServiceProvider::$publishes;
    $allPublished = collect($publishes)->flatten()->toArray();
    expect($allPublished)->not->toBeEmpty();
});
