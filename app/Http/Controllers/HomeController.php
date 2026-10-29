<?php

namespace App\Http\Controllers;

use App\Support\FeedPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The lifestream page shell (legacy index.php). The tiles themselves are
 * loaded client-side from FeedController by nicave.js.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $period = FeedPeriod::fromPath('/'.ltrim($request->path(), '/'));

        abort_if($period === null, 404);

        return view('home', ['period' => $period]);
    }
}
