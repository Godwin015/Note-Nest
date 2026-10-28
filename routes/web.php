<?php

use Illuminate\Support\Facades\Route;

// Redirect the root URL to login.
Route::redirect('/', '/login');

// Login page.
Route::view('/login', 'welcome')
    ->middleware('guest')
    ->name('login');


// Dashboard page.
Route::view('/dashboard', 'welcome')
    ->middleware('auth')
    ->name('dashboard');
