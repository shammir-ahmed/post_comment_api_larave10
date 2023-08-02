<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use App\Http\Requests\PostFormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $posts = Post::query();

        // check is there any filteration parameter
        if (request()->has('author') && strlen(request()->author) > 0) {
            $author = request()->author;
            $posts = $posts->where('author', 'LIKE', '%.$author.%');
        }

        $posts = $posts->get();

        return JsonResource::collection($posts);
    }

    /**
     * Show the form for creating a new resource.
     */

    /**
     * Store a newly created resource in storage.
     */
    public function store(PostFormRequest $request)
    {
        $data = $request->validated(); // validation and return validated data
        $post = post::create($data);

        return new JsonResource($post); // 201
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post)
    {
        return new JsonResource($post);
    }

    /**
     * Show the form for editing the specified resource.
     */
    /**
     * Update the specified resource in storage.
     */
    public function update(PostFormRequest $request, Post $post)
    {
        $post->fill($request->validated());
        $post->save(); // true or false

        return new JsonResource($post);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post)
    {
        $post->delete();

        return response()->json([], 204);
    }
}
