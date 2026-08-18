<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    // overview: GMV, trade volume, user growth, retention
    public function overview(): JsonResponse
    {
        $now = now();
        $lastMonth = $now->copy()->subMonth();

        // GMV (orders paid)
        $gmv = Order::where('payment_status', 'Paid')->sum('total');
        $gmvLastMonth = Order::where('payment_status', 'Paid')
            ->whereBetween('created_at', [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()])
            ->sum('total');

        // Trade volume
        $tradeVolume = Trade::where('status', 'completed')->count();
        $tradeVolumeLastMonth = Trade::where('status', 'completed')
            ->whereBetween('created_at', [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()])
            ->count();

        // New users
        $newUsers = User::whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
        $newUsersLastMonth = User::whereMonth('created_at', $lastMonth->month)->whereYear('created_at', $lastMonth->year)->count();

        // Active users (had order or trade this month)
        $activeUsers = User::whereHas('orders', fn($q) => $q->whereMonth('created_at', $now->month))
            ->orWhereHas('tradesAsInitiator', fn($q) => $q->whereMonth('created_at', $now->month))
            ->orWhereHas('tradesAsReceiver', fn($q) => $q->whereMonth('created_at', $now->month))
            ->count();

        return response()->json([
            'gmv' => [
                'current' => (int) $gmv,
                'lastMonth' => (int) $gmvLastMonth,
                'change' => $gmvLastMonth > 0 ? round((($gmv - $gmvLastMonth) / $gmvLastMonth) * 100, 1) : 0,
            ],
            'tradeVolume' => [
                'current' => $tradeVolume,
                'lastMonth' => $tradeVolumeLastMonth,
                'change' => $tradeVolumeLastMonth > 0 ? round((($tradeVolume - $tradeVolumeLastMonth) / $tradeVolumeLastMonth) * 100, 1) : 0,
            ],
            'newUsers' => [
                'current' => $newUsers,
                'lastMonth' => $newUsersLastMonth,
                'change' => $newUsersLastMonth > 0 ? round((($newUsers - $newUsersLastMonth) / $newUsersLastMonth) * 100, 1) : 0,
            ],
            'activeUsers' => $activeUsers,
        ]);
    }

    // kategori terlaris
    public function categories(): JsonResponse
    {
        $topCategories = Product::selectRaw('category, count(*) as total_products, sum(sold) as total_sold')
            ->groupBy('category')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get();

        $tradeCategories = Trade::where('status', 'completed')
            ->join('collections as ic', 'trades.initiator_collection_id', '=', 'ic.id')
            ->join('products as ip', 'ic.product_id', '=', 'ip.id')
            ->selectRaw('ip.category, count(*) as trade_count')
            ->groupBy('ip.category')
            ->orderByDesc('trade_count')
            ->limit(10)
            ->get();

        return response()->json([
            'sales' => $topCategories,
            'trades' => $tradeCategories,
        ]);
    }

    // top sellers
    public function sellers(): JsonResponse
    {
        $topByRevenue = User::where('is_verified_seller', true)
            ->withCount(['products', 'orders'])
            ->withSum('orders as total_revenue', 'total')
            ->whereHas('orders', fn($q) => $q->where('payment_status', 'Paid'))
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar,
                'products' => $u->products_count,
                'orders' => $u->orders_count,
                'revenue' => (int) ($u->total_revenue ?? 0),
            ]);

        $topByTrades = User::where('trades_count', '>', 0)
            ->orderByDesc('trades_count')
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar,
                'trades' => $u->trades_count,
                'rating' => $u->rating,
                'positiveRate' => $u->positive_rate,
            ]);

        return response()->json([
            'byRevenue' => $topByRevenue,
            'byTrades' => $topByTrades,
        ]);
    }

    // chart data untuk Recharts (GMV per bulan 6 bulan terakhir)
    public function charts(Request $request): JsonResponse
    {
        $months = 6;
        $start = now()->subMonths($months)->startOfMonth();

        // GMV per bulan
        $gmvData = Order::where('payment_status', 'Paid')
            ->where('created_at', '>=', $start)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn($d) => [
                'label' => $d->year . '-' . str_pad($d->month, 2, '0', STR_PAD_LEFT),
                'gmv' => (int) $d->total,
            ]);

        // Orders per bulan
        $ordersData = Order::where('created_at', '>=', $start)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn($d) => [
                'label' => $d->year . '-' . str_pad($d->month, 2, '0', STR_PAD_LEFT),
                'orders' => (int) $d->total,
            ]);

        // Trades per bulan
        $tradesData = Trade::where('status', 'completed')
            ->where('created_at', '>=', $start)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn($d) => [
                'label' => $d->year . '-' . str_pad($d->month, 2, '0', STR_PAD_LEFT),
                'trades' => (int) $d->total,
            ]);

        // New users per bulan
        $usersData = User::where('created_at', '>=', $start)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn($d) => [
                'label' => $d->year . '-' . str_pad($d->month, 2, '0', STR_PAD_LEFT),
                'users' => (int) $d->total,
            ]);

        return response()->json([
            'gmv' => $gmvData,
            'orders' => $ordersData,
            'trades' => $tradesData,
            'users' => $usersData,
        ]);
    }

    // export laporan bulanan
    public function export(Request $request): JsonResponse
    {
        $month = $request->query('month', now()->format('Y-m'));
        [$year, $m] = explode('-', $month);

        $start = now()->setDate($year, $m, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Orders
        $orders = Order::with('user:id,name,email')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        // Trades
        $trades = Trade::with([
            'initiator:id,name',
            'receiver:id,name',
            'initiatorCollection.product:id,name',
            'receiverCollection.product:id,name',
        ])->whereBetween('created_at', [$start, $end])->get();

        // CSV Orders
        $orderHeaders = ['Order Number', 'Customer', 'Status', 'Payment', 'Total', 'Date'];
        $orderRows = $orders->map(fn($o) => [
            $o->order_number,
            $o->user->name ?? '-',
            $o->status,
            $o->payment_status,
            'Rp' . number_format($o->total, 0, ',', '.'),
            $o->created_at?->format('Y-m-d'),
        ]);

        $orderCsv = implode(',', array_map(fn($h) => '"' . $h . '"', $orderHeaders)) . "\n";
        $orderCsv .= $orderRows->map(fn($r) => implode(',', array_map(fn($c) => '"' . $c . '"', $r)))->implode("\n");

        // CSV Trades
        $tradeHeaders = ['ID', 'Status', 'Initiator', 'Receiver', 'Cash Diff', 'Date'];
        $tradeRows = $trades->map(fn($t) => [
            'TRD-' . str_pad($t->id, 6, '0', STR_PAD_LEFT),
            $t->status,
            $t->initiator->name ?? '-',
            $t->receiver->name ?? '-',
            'Rp' . number_format(abs($t->cash_difference), 0, ',', '.') . ($t->cash_difference < 0 ? ' (receiver)' : ' (initiator)'),
            $t->created_at?->format('Y-m-d'),
        ]);

        $tradeCsv = implode(',', array_map(fn($h) => '"' . $h . '"', $tradeHeaders)) . "\n";
        $tradeCsv .= $tradeRows->map(fn($r) => implode(',', array_map(fn($c) => '"' . $c . '"', $r)))->implode("\n");

        // Combine (or return zip in real app)
        $combined = "=== ORDERS ===\n" . $orderCsv . "\n\n=== TRADES ===\n" . $tradeCsv;

        return response($combined, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"report-{$month}.csv\"",
        ]);
    }
}
