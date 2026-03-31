<?php
namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $client = $user ? $user->clients()->first() : null;
        return view("client.home", compact("client"));
    }
}
