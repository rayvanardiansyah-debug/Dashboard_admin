<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Book;

class BookController extends Controller
{
    /**
     * Menampilkan daftar buku.
     */
    public function index()
    {
        return response()->json(Book::all(), 200);
    }

    /**
     * Menyimpan buku baru.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|integer',
        ]);

        $book = Book::create($validatedData);
        return response()->json($book, 201);
    }

    /**
     * Menampilkan detail buku berdasarkan ID.
     */
    public function show(string $id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'Buku tidak ditemukan'], 404);
        }
        return response()->json($book, 200);
    }

    /**
     * Memperbarui buku.
     */
    public function update(Request $request, string $id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'Buku tidak ditemukan'], 404);
        }
        $book->update($request->all());
        return response()->json($book, 200);
    }

    /**
     * Menghapus buku.
     */
    public function destroy(string $id)
    {
        $book = Book::find($id);
        if (!$book) {
            return response()->json(['message' => 'Buku tidak ditemukan'], 404);
        }
        $book->delete();
        return response()->json(['message' => 'Buku berhasil dihapus'], 200);
    }
}
