<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FilesController extends Controller
{
    public function index(Request $request) {
        $filesTab = array();

        $model = "\App\Models\\".$request->model;
        
        $files = $model::find($request->id);
        $filesTab = json_decode($files->photos);
        return $filesTab;
    }

    public function carWithdrawalFiles() {

    }
}
