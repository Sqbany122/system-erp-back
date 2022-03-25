<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\PipelineAdditionalInfo;
use App\Models\PipelineComment;
use App\Models\PipelineFiles;
use App\Http\Controllers\GusController;
use App\Models\ProjectAdditionalInfo;
use App\Models\ProjectFiles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\LogsController;


class PipelineController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPipelines()
    {           
        $user = Auth::user();
        if ($user->can("pipelines_all")) {
            $pipeline = Pipeline::get();
        } else {
            $pipeline = Pipeline::where('owner', $user->id)->get();
        }

        LogsController::addLog(['event' => 'show', 'model' => 'Pipeline']);

        return $pipeline;
    }

    public function getSinglePipeline(Request $request)
    {
        $pipeline = Pipeline::find($request->pipelineId);
        $pipeline_additional = PipelineAdditionalInfo::where('pipeline', $request->pipelineId)->first();

        $pipeline_comments = PipelineComment::where('pipeline', $request->pipelineId)
                                                    ->where('private', 0)
                                                    ->orWhere(fn ($query) => 
                                                        $query->where('pipeline', $request->pipelineId)
                                                                ->where('private', 1)
                                                                ->where('owner', Auth::id())
                                                    )
                                                    ->get();

        $pipeline_files = PipelineFiles::where('pipeline', $request->pipelineId)->get();                                         

        $gus = new GusController();
        $result = json_decode($gus->checkNip($pipeline->nip));

        $pipeline->gus_data = $result->dane;
        $pipeline->additional_info = $pipeline_additional;
        $pipeline->comments = $pipeline_comments;
        $pipeline->files = $pipeline_files;

        LogsController::addLog(['event' => 'show', 'model' => 'Pipeline']);

        return $pipeline;
    }

    public function updatePipelineAdditionalInfo(Request $request) {
        $additionalInfo = PipelineAdditionalInfo::where('pipeline', $request->pipeline)->first();
        $additionalInfo->contact_person = $request->contact_person;
        $additionalInfo->phone = $request->phone;
        $additionalInfo->email = $request->email;
        $additionalInfo->address = $request->address;
        $additionalInfo->city = $request->city;
        $additionalInfo->postal_code = $request->postal_code;
        $additionalInfo->note = $request->note;
        $additionalInfo->save();

        $request = new Request([
            'pipelineId' => $request->pipeline,
        ]);

        $pipeline = $this->getSinglePipeline($request);
        
        LogsController::addLog(['event' => 'edit', 'model' => 'PipelineAdditionalInfo', 'element_id' => [$additionalInfo->id]]);

        return $pipeline;
    }

    public function addComment(Request $request) {
        $pipeline_comment = new PipelineComment();

        $pipeline_comment->pipeline = $request->pipeline;
        $pipeline_comment->owner = Auth::id();
        $pipeline_comment->comment = $request->comment;
        if ($request->private) {
            $pipeline_comment->private = 1;
        } 
        $pipeline_comment->save();

        $request = new Request([
            'pipelineId' => $request->pipeline,
        ]);

        $pipeline = $this->getSinglePipeline($request);
        return $pipeline;
    }

    public function addFile(Request $request) {
        $fileName = Storage::disk('public')->put('pipeline_files/', $request->file);
        $fileName = Str::replace('pipeline_files//', '', $fileName);
        $pipelineFile = new PipelineFiles;
        $pipelineFile->pipeline = $request->pipeline;
        $pipelineFile->owner = Auth::id();
        $pipelineFile->file_path = $fileName;
        $pipelineFile->file_extension = strtoupper($request->file->extension());
        $pipelineFile->file_description = $request->file_description;
        $pipelineFile->save();

        $request = new Request([
            'pipelineId' => $request->pipeline,
        ]);

        $pipeline = $this->getSinglePipeline($request);
        return $pipeline;
    }

    public function downloadFile($name) {
        $path = storage_path('app/public/pipeline_files/'.$name);
        return response()->download($path);
    }

    public function changeStatus(Request $request) {
        $pipeline = Pipeline::find($request->pipelineId);
        
        $pipeline->status = $request->status;
        $pipeline->save();

        if ($pipeline->save() && $request->status == 5) {
            $project = new Project;

            $project->company = $request->company;
            $project->owner = $request->owner;
            $project->name = $pipeline->name;
            $project->nip = $pipeline->nip;
            $project->pipeline_owner = $pipeline->owner;
            $project->save();

            $project = $project->fresh();

            if ($project->save()) {
                $additionalInfoPipeline = PipelineAdditionalInfo::where('pipeline', $request->pipelineId)->first();

                $additionalInfoProject = new ProjectAdditionalInfo();
                $additionalInfoProject->insert([
                    'project' => $project->id,
                    'contact_person' => $additionalInfoPipeline->contact_person,
                    'phone' => $additionalInfoPipeline->phone,
                    'email' => $additionalInfoPipeline->email,
                    'address' => $additionalInfoPipeline->address,
                    'city' => $additionalInfoPipeline->city,
                    'postal_code' => $additionalInfoPipeline->postal_code,
                    'note' => $additionalInfoPipeline->note,
                    'created_at' =>  \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now()
                ]);

                $pipelineFiles = PipelineFiles::where('pipeline', $request->pipelineId)->get();
                foreach ($pipelineFiles as $file) {
                    ProjectFiles::insert([
                        'project' => $project->id,
                        'owner' => $file->owner,
                        'file_path' => $file->file_path,
                        'file_extension' => $file->file_extension,
                        'file_description' => $file->file_description,
                        'created_at' =>  \Carbon\Carbon::now(),
                        'updated_at' => \Carbon\Carbon::now()
                    ]);
                }
            }   
        }

        LogsController::addLog(['event' => 'change_status', 'model' => 'Pipeline', 'element_id' => [$pipeline->id]]);


        $pipeline = $pipeline->fresh();

        return $pipeline;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $gus = new GusController();
        $result = json_decode($gus->checkNip($request->nip));

        $pipeline = new Pipeline;
        $pipeline->owner = Auth::id();
        $pipeline->name = $result->dane->Nazwa;
        $pipeline->nip = $request->nip;
        
        if ($pipeline->save()) {
            $pipeline_additional = new PipelineAdditionalInfo;
            $pipeline_additional->pipeline = $pipeline->id;
            $pipeline_additional->save();
        }

        $pipeline = $pipeline->fresh();

        LogsController::addLog(['event' => 'add', 'model' => 'Pipeline', 'element_id' => [$pipeline->id]]);


        return $pipeline;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
