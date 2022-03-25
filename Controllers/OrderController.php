<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Status;
use App\Models\Currency;
use App\Models\OrderComment;
use App\Models\OrderFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\LogsController;
use App\Models\Project;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function getorders(Request $request)
    {
        $orders = Order::get();

        foreach ($orders as $order) {
            if ($order->project) { 
                $project = Project::find($order->project);
                if ($project) {
                    $order->project_group = $project->record;
                } else {
                    $order->project_group = NULL;
                }
            }
        }

        $this->calculatePriceFromNBP($orders);

        LogsController::addLog(['event' => 'show', 'model' => 'Order']);

        return $orders;
    }

    public function getOrderProjectGroup($order) { 
        $project = Project::find($order->project);
        if ($project) {
            return $project->record;
        } else {
            return NULL;
        }
    }

    public function getSingleOrder(Request $request)
    {
        $order = Order::find($request->orderId);
        $this->calculatePriceFromNBP($order);

        $order->email = Auth::user()->email;

        LogsController::addLog(['event' => 'show', 'model' => 'Order']);

        return $order;
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
        $order = new Order;
        $order->owner = Auth::id();
        $order->name = $request->name;
        $order->description = $request->description;
        $order->price = $request->price;
        $order->currency = $request->currency;
        $order->project = $request->project;
        $order->order_category = $request->order_category;
        $order->save();

        $order = $order->fresh();
        $this->calculatePriceFromNBP($order);

        LogsController::addLog(['event' => 'add', 'model' => 'Order', 'element_id' => [$order->id]]);


        return $order;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $order = Order::find($request->id);
        $order->name = $request->name;
        $order->description = $request->description;
        $order->price = $request->price;    
        $order->currency = $request->currency;
        $order->priority = $request->priority;
        $order->project = $request->project;
        $order->order_category = $request->order_category;
        $order->save();

        $order = $order->fresh();
        $this->calculatePriceFromNBP($order);
        $order->project_group = $this->getOrderProjectGroup($order);

        LogsController::addLog(['event' => 'edit', 'model' => 'Order', 'element_id' => [$order->id]]);

        return $order;
    }

    /**
     * Update status of an order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateOrderStatus(Request $request) {
        $order = Order::find($request->orderId);
        $status = Status::where('name', $request->status)->first("id");
        $order->status = $status->id;
        $order->save();

        $this->calculatePriceFromNBP($order);

        LogsController::addLog(['event' => 'change_status', 'model' => 'Order', 'element_id' => [$order->id]]);

        return $order;
    }

    /**
     * Update status of an order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function acceptOrder(Request $request) {
        $order = Order::find($request->id);
        $status = Status::where('name', $request->status)->first("id");
        $order->priority = $request->priority;
        $order->status = $status->id;
        $order->save();

        $this->calculatePriceFromNBP($order);

        LogsController::addLog(['event' => 'change_status', 'model' => 'Order', 'element_id' => [$order->id]]);

        return $order;
    }

    /**
     * Update status of an order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getComments(Request $request) {
        $orderComment = OrderComment::where('order', $request->orderId)->get();

        return $orderComment;
    }

    /**
     * Update status of an order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addComment(Request $request) {
        $orderComment = new OrderComment;
        $orderComment->order = $request->order;
        $orderComment->owner = Auth::id();
        $orderComment->comment = $request->comment;
        $orderComment->save();

        return $orderComment;
    }

    /**
     * Update status of an order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getFiles(Request $request) {
        $orderFiles = OrderFiles::where('order', $request->orderId)->get();

        return $orderFiles;
    }

    public function downloadFile($name) {
        $path = storage_path('app/public/order_files/'.$name);

        return response()->download($path);
    }

    /**
     * Update status of an order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addFile(Request $request) {
        $fileName = Storage::disk('public')->put('order_files/', $request->file);
        $fileName = Str::replace('order_files//', '', $fileName);
        $orderFile = new OrderFiles;
        $orderFile->order = $request->order;
        $orderFile->owner = Auth::id();
        $orderFile->file_path = $fileName;
        $orderFile->file_description = $request->file_description;
        $orderFile->save();

        return $orderFile;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        Order::whereIn('id', $request)->delete();
        OrderComment::whereIn('order', $request)->delete();

        LogsController::addLog(['event' => 'delete', 'model' => 'Order', 'element_id' => $request->all()]);

        return $request;
    }

    private function calculatePriceFromNBP($order) {
        $json = json_decode(file_get_contents('https://api.nbp.pl/api/exchangerates/tables/A'), true);

        if (is_countable($order)) {
            foreach ($order as $ord) {
                $currency = Currency::where("id", $ord->currency)->first("name");
                foreach ($json[0]["rates"] as $rate) {
                    if ($currency->name !== "PLN" && $currency->name === $rate["code"]) {
                        $ord->orginal_price = number_format($ord->price, 2, '.', '');
                        $ord->price = number_format(($ord->price * $rate["mid"]), 2, '.', '');
                    }
                    $ord->currency_name = $currency->name;
                }
            }
        } else {
            $currency = Currency::where("id", $order->currency)->first("name");
            foreach ($json[0]["rates"] as $rate) {
                if ($currency->name !== "PLN" && $currency->name === $rate["code"]) {
                    $order->orginal_price = number_format($order->price, 2, '.', '');
                    $order->price = number_format(($order->price * $rate["mid"]), 2, '.', '');
                }
                $order->currency_name = $currency->name;
            }
        }
    }
}
