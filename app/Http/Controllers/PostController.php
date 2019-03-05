<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class PostController extends Controller
{
    public function store(Request $request){
        try {
            $user = \JWTAuth::parseToken()->authenticate();

            $validate = \Validator::make($request->only('name', 'description'),[
                'name' => 'required|string|max:50',
                'description' => 'string|max:255',
            ]);

            if($validate->fails()){
                return \Response::json([
                    'success' => false,
                    'message' => 'Error al validar los campos',
                    'error' => $validate->errors()
                ], 400);
            }

            \DB::beginTransaction();
            $idPost = \DB::table('posts')
                ->insertGetId([
                    'idUsuario' => $user->id,
                    'name' => $request->name,
                    'description' => $request->description
                ]);
            
            $imagen64 = null;
            $textContent = null;
            if($request->image){
                $imagen64 = null;
                $mime = null;
                if(isset($request->image64)){
                    $path = $request->file("image64")->path();
                    $mime = \File::mimeType($path);
                    $img = \File::get($path);
                    $imagen64 = base64_encode($img);
                }
            }
            if($request->texto){
                if(isset($request->textContent)){
                    $textContent = $request->textContent;
                }
            }

            $idComplement = \DB::table('complement')
                ->insertGetId([
                    'idPost' => $idPost,
                    'image' => (int)($request->image),
                    'image64' => $imagen64,
                    'texto' => (int)($request->texto),
                    'textContent' => $textContent
                ]);

            \DB::table('posts')
                ->where('id', $idPost)
                ->update([
                    'idComplemento' => $idComplement
                ]);
            \DB::commit();
            return \Response::json([
                'success' => true,
                'message' => 'Post creado satisfactoriamente',
                'idPost' => $idPost
            ], 200);
        } catch (\Exception $e) {
            \Log::info('Error al crear un post');
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeComent (Request $request){
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            $validate = \Validator::make($request->only('content'),[
                'content' => 'required|string|max:255',
            ]);

            if($validate->fails()){
                return \Response::json([
                    'success' => false,
                    'message' => 'Error al validar los campos',
                    'error' => $validate->errors()
                ], 400);
            }

            $idComent = \DB::table('coments')
                ->insertGetId([
                    'idPost' => $request->idPost,
                    'content' => $request->content,
                    'idUser' => $user->id
                ]);

            return \Response::json([
                'success' => true,
                'message' => 'Comentario creado satisfactoriamente',
                'idComent' => $idComent
            ], 200);
        } catch (\Exception $e) {
            \Log::info('Error al comentar un post'. $e);
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteComent($idComent){
        try {
            $user = \JWTAuth::parseToken()->authenticate();   
            $coment = \DB::table('coments')
                ->where('id', $idComent)
                ->first();

            $post = \DB::table('posts')
                ->where('id', $coment->idPost)
                ->first();

            if($coment->idUser == $user->id){
                \DB::table('coments')
                    ->where('id', $idComent)
                    ->update(['activo' => 0]);
            }else if($post->idUsuario == $user->id){
                \DB::table('coments')
                    ->where('id', $idComent)
                    ->update(['activo' => 0]);
            }else{
                return \Response::json([
                    'success' => false,
                    'message' => 'No está autorizado para eliminar este comentario'
                ], 403);
            }
            return \Response::json([
                'success' => true,
                'message' => 'Comentario eliminado con éxito'
            ], 200);
        } catch (\Exception $e) {
            \Log::info('Error al eliminar comentarios');
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showByUser($idUser){
        try {
            $user = \DB::table('users')
                ->where('id', $idUser)
                ->get();

            $posts = \DB::table('posts')
                ->leftJoin('complement', 'posts.idComplemento', 'complement.id')
                ->where('posts.idUsuario', $idUser)
                ->select('posts.id as postId', 'posts.name as postName', 'posts.description as description',
                'complement.id as complementId', 'complement.image64 as image', 'complement.textContent as text')
                ->where('posts.activo', 1)
                ->get();

            foreach ($posts as $post) {
                $coments = \DB::table('coments')
                    ->where('idPost', $post->postId)
                    ->where('activo', 1)
                    ->select('id', 'content')
                    ->get();
                $comentsCount = \DB::table('coments')
                    ->where('idPost', $post->postId)
                    ->select('id', 'content')
                    ->where('activo', 1)
                    ->count();
                $post->coments = $coments;
                $post->countComents = $comentsCount;
                $likes = \DB::table('likes')
                ->where('idPost', $post->postId)
                ->select('id')
                ->where('activo', 1)
                ->count();
                $dislikes = \DB::table('dislikes')
                ->where('idPost', $post->postId)
                ->select('id')
                ->where('activo', 1)
                ->count();
                $post->likes = $likes;
                $post->dislikes = $dislikes;
            }

            return \Response::json([
                'success' => true,
                'user' => $user,
                'posts' => $posts
            ], 200);
        } catch (\Exception $e) {
            \Log::info('Error al mostrar los post del usuario ' . $e);
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function like($idPost){
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            $likes = \DB::table('likes')
                ->where('idUsuario', $user->id)
                ->where('idPost', $idPost)
                ->where('activo', 1)
                ->get();
                
            if(count($likes) <= 0){
                $likeId = \DB::table('likes')
                    ->insertGetId([
                        'idPost' => $idPost,
                        'idUsuario' => $user->id
                    ]);
                return \Response::json([
                    'success' => true,
                    'message' => 'Dio like al post',
                    'likeId' => $likeId
                ], 200);
            }else{
                \DB::table('likes')
                    ->where('idUsuario', $user->id)
                    ->where('idPost', $idPost)
                    ->update([
                        'activo' => 0
                    ]);
                return \Response::json([
                    'success' => false,
                    'message' => 'Quitó el like al post'
                ], 200);
            }
        } catch (\Exception $e) {
            \Log::info('Error al darle like a un post ' . $e);
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function dislike($idPost){
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            $dislikes = \DB::table('dislikes')
                ->where('idUser', $user->id)
                ->where('idPost', $idPost)
                ->where('activo', 1)
                ->get();
                
            if(count($dislikes) <= 0){
                $likeId = \DB::table('dislikes')
                    ->insertGetId([
                        'idPost' => $idPost,
                        'idUser' => $user->id
                    ]);
                return \Response::json([
                    'success' => true,
                    'message' => 'Dio dislike al post',
                    'likeId' => $likeId
                ], 200);
            }else{
                \DB::table('dislikes')
                    ->where('idUser', $user->id)
                    ->where('idPost', $idPost)
                    ->update([
                        'activo' => 0
                    ]);
                return \Response::json([
                    'success' => false,
                    'message' => 'Quitó el dislike al post'
                ], 401);
            }
        } catch (\Exception $e) {
            \Log::info('Error al darle like a un post ' . $e);
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(){
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            
            $seguidores = \DB::table('follow')
                ->where('idUser', $user->id)
                ->where('activo', 1)
                ->select('idSeguidor')
                ->get();
            $seguidoresId = array();
            foreach ($seguidores as $seg) {
                array_push($seguidoresId, $seg->idSeguidor);
            }
            $posts = \DB::table('posts')
                ->leftJoin('complement', 'posts.idComplemento', 'complement.id')
                ->select('posts.id as postId', 'posts.name as postName', 'posts.description as description',
                'complement.id as complementId', 'complement.image64 as image', 'complement.textContent as text')
                ->where('posts.activo', 1)
                ->where(function($query) use ($user, $seguidoresId){
                    $query->where('posts.idUsuario', $user->id)
                        ->orWhereIn('posts.idUsuario', $seguidoresId);
                })
                ->get();

            foreach ($posts as $post) {
                $coments = \DB::table('coments')
                    ->where('idPost', $post->postId)
                    ->where('activo', 1)
                    ->select('id', 'content')
                    ->get();
                $comentsCount = \DB::table('coments')
                    ->where('idPost', $post->postId)
                    ->select('id', 'content')
                    ->where('activo', 1)
                    ->count();
                $post->coments = $coments;
                $post->countComents = $comentsCount;
                $likes = \DB::table('likes')
                ->where('idPost', $post->postId)
                ->select('id')
                ->where('activo', 1)
                ->count();
                $dislikes = \DB::table('dislikes')
                ->where('idPost', $post->postId)
                ->select('id')
                ->where('activo', 1)
                ->count();
                $post->likes = $likes;
                $post->dislikes = $dislikes;
            }

            return \Response::json([
                'success' => true,
                'posts' => $posts
            ], 200);
        } catch (\Exception $e) {
            \Log::info('Error al mostrar los post del usuario ' . $e);
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
