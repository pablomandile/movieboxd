<?php

namespace App\Http\Controllers;

use App\Support\PageMeta;
use Inertia\Inertia;
use Inertia\Response;

class AboutController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('About', [
            'meta' => PageMeta::make(
                title: __('app.about_title').' · '.config('app.name'),
                description: __('app.about_description'),
            ),
        ]);
    }
}
