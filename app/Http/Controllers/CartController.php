<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Detail_penjualan;
use App\Models\Produks;
use App\Models\Penjualan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;

class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    function index()
    {
        $user = Auth::user();
        return view('customer.detailCart', compact('user'));
    }
    function read()
    {
        $user = Auth::user();
        // $data = Cart::where('id_user', $user->id)->get();
        // $produk = Produks::whereIn('id', $data->pluck('id_produk'))->get();
        $data = Cart::where('id_user', $user->id)->join('Produks', 'Cart.id_produk', '=', 'Produks.id')
        ->select('Cart.id','Cart.id_produk', 'Produks.name', 'Cart.id_user', 'Cart.harga', 'Cart.jumlah', 'Cart.size', 'Produks.foto')
        ->get();
        // return view('customer.read', compact('data', 'produk'));
        // dd($data);
        return view('customer.read', compact('data'));
    }


    function show($id)
    {
        $data = Produks::find($id);
        return view('customer.shopping-cart', compact('data'));
    }    

    function addCart(Request $request, $id)
    {
        $jumlah = $request->input('jumlah');
        $ukuran = $request->input('ukuran');
        $customer = Auth::user()->id;
        
        $existingCartItem = Cart::where('id_user', $customer)
            ->where('id_produk', $id)
            ->where('size', $ukuran) 
            ->first();

        if ($existingCartItem) {
            // Jika produk dengan ukuran large sudah ada, tambahkan jumlahnya
            $newQuantity = $existingCartItem->jumlah + $jumlah;

            // Cek apakah jumlah baru melebihi stok
            if ($newQuantity <= $request->stok) {
                $existingCartItem->jumlah = $newQuantity;
                $existingCartItem->save();
            } else {
                return response()->json(['error' => 'Out Of Stock'], 400);
            }
        } else {
            // Jika produk dengan ukuran large belum ada, buat entri baru dalam keranjang
            if ($jumlah <= $request->stok) {
                $data = new Cart;
                $data->id_user = $customer;
                $data->id_produk = $id;
                $data->harga = $request->harga;
                $data->jumlah = $jumlah;
                $data->size = $ukuran;
                $data->save();
            } else {
                return response()->json(['error' => 'Out Of Stock'], 400);
            }
        }

        return view('menu');
    }



    public function hitungHarga(Request $request)
    {
        $ukuran = $request->input('ukuran');
        $jumlah = $request->input('jumlah');
        $idProduk = $request->input('idProduk');

        $produk = Produks::find($idProduk);
        $hargaAwal = $produk->harga;

        if ($ukuran === "medium") {
            $persentase = 5;
        } elseif ($ukuran === "large") {
            $persentase = 10;
        } else {
            $persentase = 0;
        }

        $hargaBaru = $hargaAwal + ($hargaAwal * ($persentase / 100));

        return response()->json(['harga' => $hargaBaru]);
    }


    function cekout(Request $request)
    {
        $id_transaksi = Penjualan::tambahTransaksi();

        if (!is_numeric($id_transaksi)) {
            return response()->json(['error' => 'Invalid id_transaksi'], 400);
        }

        $selectedItems = $request->input('items');
        $items = [];
        foreach ($selectedItems as $itemId) {
            $dataMenu = Cart::find($itemId);
            $total = $dataMenu->harga * $dataMenu->jumlah;
            if (!$dataMenu) {
                return response()->json(['error' => 'Item not found'], 404);
            }

            $items[] = [
                'id_transaksi' => $id_transaksi,
                'name' => $dataMenu->id_user,
                'produk' => $dataMenu->id_produk,
                'quantity' => $dataMenu->jumlah,
                'price' => $total,
                'size' => $dataMenu->size,
            ];
            
            $data = new Detail_penjualan;
            $data->id_transaksi = $id_transaksi; 
            $data->id_menu = $dataMenu->id_produk;
            $data->jumlah = $dataMenu->jumlah;
            $data->harga_penjualan = $total;
            $data->size = $dataMenu->size;            
            $data->save();

            $produk = Produks::find($data->id_menu);
            $produk->stok = $produk->stok - $dataMenu->jumlah;
            $produk->save();

            $dataMenu->delete();

        }
        $response = ['items' => $items, 'data'=>$data]; // Create a response array with 'items' key
        return response()->json($response); // Send JSON response to the client
    }

    function qrcode(Request $request)
    {
        $qrCodeData = $request->input('data');
        return view('customer.pembayaran', compact('qrCodeData'));
    }

    function destroy(Request $request)
    {
        $selectedItems = $request->input('items');

        foreach ($selectedItems as $itemId) {
            $data = Cart::find($itemId);
            $data->delete();
        }

        return response()->json(['message' => 'Items deleted successfully'], 200);
    }

}