<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use App\Http\Requests\CommentFormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $comments = Comment::query();

        // check is there any filteration parameter
        if (request()->has('author') && strlen(request()->author) > 0) {
            $author = request()->author;
            $comments = $comments->where('author', 'LIKE', '%.$author.%');
        }

        $comments = $comments->get();

        return JsonResource::collection($comments);
    }

    /**
     * Show the form for creating a new resource.
     */

    /**
     * Store a newly created resource in storage.
     */
    public function store(CommentFormRequest $request)
    {
        $data = $request->validated(); // validation and return validated data
        $comment = Comment::create($data);

        return new JsonResource($comment); // 201
    }

    /**
     * Display the specified resource.
     */
    public function show(Comment $comment)
    {
        return new JsonResource($comment);
    }

    /**
     * Show the form for editing the specified resource.
     */
   
    /**
     * Update the specified resource in storage.
     */
    public function update(CommentFormRequest $request, Comment $comment)
    {
        $comment->fill($request->validated());
        $comment->save(); // true or false

        return new JsonResource($comment);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comment $comment)
    {
        $comment->delete();

        return response()->json([], 204);
    }
}
