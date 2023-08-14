<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Database\Query\Builder;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/aircraft_airports', function (Request $request) {

    // Fetch flights data

    $flights = DB::table('flights')
        ->join('aircrafts', 'flights.aircraft_id', '=', 'aircrafts.id')
        ->select('flights.id', 'airport_id1', 'airport_id2', 'cargo_offload', 'cargo_load', 'landing', 'takeoff')
        ->where('tail', $request->input('tail'))
        ->get();

    // Transform flights data to airports array

    $airports = [];

    foreach ($flights as $index => $flight) {

        $airport = DB::table('airports')
            ->select('id', 'code_iata', 'code_icao')
            ->where('id', $flight->airport_id1)
            ->first();

        $airports[] = [
            "airport_id" => $flight->airport_id1,
            "code_iata" => $airport->code_iata,
            "code_icao" => $airport->code_icao,
            "cargo_offload" => $flights[$index - 1]->cargo_offload ?? 0,
            "cargo_load" => $flight->cargo_load,
            "landing" => $flights[$index - 1]->landing ?? 0,
            "takeoff" => $flight->takeoff
        ];

        // Add last airport

        if ($index === count($flights) - 1) {
            $airport = DB::table('airports')
                ->select('id', 'code_iata', 'code_icao')
                ->where('id', $flight->airport_id2)
                ->first();

            $airports[] = [
                "airport_id" => $flight->airport_id2,
                "code_iata" => $airport->code_iata,
                "code_icao" => $airport->code_icao,
                "cargo_offload" => $flight->cargo_offload,
                "cargo_load" => 0,
                "landing" => $flight->landing,
                "takeoff" => 9999999999999
            ];
        }
    }

    // Filter airports with given time range

    $startDate = strtotime($request->input('date_from'));
    $endDate = strtotime($request->input('date_to'));

    $airports = array_filter($airports, function ($airport) use ($startDate, $endDate) {
        $landingDate = strtotime($airport['landing']);
        $takeoffDate = strtotime($airport['takeoff']);

        if (($startDate <= $takeoffDate) && ($takeoffDate <= $endDate)) return true;
        else if (($startDate <= $landingDate) && ($landingDate <= $endDate)) return true;
        else if (($landingDate <= $startDate) && ($endDate <= $takeoffDate)) return true;

        return false;
    });

    return response()->json($airports);
});

