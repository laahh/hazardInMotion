<?php

namespace App\Http\Controllers\ScoreCard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ScoreCardController extends Controller
{
    public function index(){
        return view('ScoreCard.index');
    }

    public function show($parameter){
        // Decode URL encoded parameter if needed, or just pass it to view
        // In a real app, you might fetch specific data based on the parameter name
        $title = urldecode($parameter);
        return view('ScoreCard.detail', compact('title'));
    }
}
