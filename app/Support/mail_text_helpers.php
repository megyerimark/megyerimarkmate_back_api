<?php

// Rövid segédfüggvény a levélsablonokhoz (az AppServiceProvider tölti be).
if (! function_exists('mt')) {
    function mt(string $hu, array $replace = []): string
    {
        return \App\Support\MailText::t($hu, $replace);
    }
}
