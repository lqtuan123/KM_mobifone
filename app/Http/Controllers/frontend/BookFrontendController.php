<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Tag;
use App\Modules\Book\Models\Book;
use App\Modules\Book\Models\BookType;
use App\Modules\Resource\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookFrontendController extends Controller
{
    // Hiển thị danh sách sách trên frontend
    public function index(Request $request)
    {
        $books = Book::with('user', 'bookType')->paginate(10);
        $booktypes = BookType::withCount('books')->where('status', 'active')->get(); // Lấy danh mục sách
        $featuredBooks = Book::with('user')->limit(5)->get(); // Lấy sách nổi bật
        $query = Book::query();

        // Xử lý tìm kiếm cơ bản
        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        // Lấy sách đề cử
        $recommendedBooks = Book::where('views', '>', 0)
            ->orderBy('views', 'desc')
            ->limit(6)
            ->get();

        // Lấy sách vừa đọc
        $recentBookIds = session()->get('recent_books', []);
        $recentBooks = Book::whereIn('id', $recentBookIds)
            ->get()
            ->sortBy(function ($book) use ($recentBookIds) {
                return array_search($book->id, $recentBookIds);
            });

        $books = $query->paginate(12);

        return view('frontend.book.index', compact('books', 'booktypes', 'featuredBooks', 'recommendedBooks', 'recentBooks'));
    }

    // Hiển thị chi tiết sách trên frontend
    public function show($slug)
    {
        // Tìm sách theo slug
        $book = Book::with('user', 'bookType')->where('slug', $slug)->firstOrFail();

        // Lấy danh mục sách
        $booktypes = BookType::withCount('books')->where('status', 'active')->get();

        // Lấy sách nổi bật
        $featuredBooks = Book::with('user')->limit(5)->get();

        // Lấy danh sách bình luận dựa vào book_id
        $comments = \App\Modules\Tuongtac\Controllers\TCommentController::getCommentActive($book->id, 'book');

        // Lấy tài nguyên liên quan
        $resourceIds = json_decode($book->resources, true)['resource_ids'] ?? [];
        $resources = Resource::whereIn('id', $resourceIds)->get();

        // Lấy các tag gắn với sách
        $tags = DB::table('tag_books')->where('book_id', $book->id)->pluck('tag_id');
        $tagNames = Tag::whereIn('id', $tags)->pluck('title');

        $book = Book::where('slug', $slug)->firstOrFail();

        // Tăng lượt xem
        DB::transaction(function () use ($book) {
            $book->views = $book->views + 1;
            $book->save();
        });

        // Lưu sách vừa đọc vào session
        $recentBooks = session()->get('recent_books', []);
        array_unshift($recentBooks, $book->id);
        $recentBooks = array_unique(array_slice($recentBooks, 0, 5));
        session()->put('recent_books', $recentBooks);


        return view('frontend.book.show', compact('book', 'resources', 'tagNames', 'comments', 'booktypes', 'featuredBooks'));
    }

    public function saveBookComment(Request $request)
    {
        $request->merge(['item_code' => 'book']);
        $commentController = new \App\Modules\Tuongtac\Controllers\TCommentController();
        return $commentController->saveComment($request);
    }
    public function booksByType($slug)
    {
        $bookType = BookType::where('slug', $slug)->firstOrFail();
        $books = Book::where('book_type_id', $bookType->id)->paginate(10);
        $booktypes = BookType::withCount('books')->where('status', 'active')->get();
        $featuredBooks = Book::with('user')->limit(5)->get();

        return view('frontend.book.by-type', compact('bookType', 'books','booktypes','featuredBooks'));
    }

    public function advancedSearch(Request $request)
{
    $tags = Tag::orderBy('title')->get();
    
    $query = Book::query();
    
    // Tìm theo tiêu đề sách
    if ($request->filled('book_title')) {  // Đổi từ 'title' thành 'book_title'
        $query->where('title', 'like', '%' . $request->book_title . '%');
    }

    if ($request->filled('author')) {
        $query->where('author', 'like', '%' . $request->author . '%');
    }
    
    // Tìm theo summary
    if ($request->filled('summary')) {  // Đổi từ 'description' thành 'summary'
        $query->where('summary', 'like', '%' . $request->summary . '%');
    }
    
    // Tìm theo tags
    if ($request->filled('tags')) {
        $query->whereHas('tags', function($q) use ($request) {
            $q->whereIn('tags.id', $request->tags);
        }, '=', count($request->tags));
    }
    
    $books = $query->with('tags')
                   ->paginate(12);
    
    return view('frontend.book.advanced-search', compact('books', 'tags'));
}
}
