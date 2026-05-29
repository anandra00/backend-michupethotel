<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SitterPackage;

class SitterPackageController extends Controller
{
    public function index()
    {
        return response()->json(SitterPackage::all());
    }
}
