<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\User;

class AuthController extends Controller
{
    public function register(Request $request){
        try {
            $validate = \Validator::make($request->all(),[
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:4|max:50',
                'name' => 'required|string|max:100',
                'lastname' => 'required|string|max:100',
                'username' => 'required|string|max:50',
            ]);

            if($validate->fails()){
                return \Response::json([
                    'success' => false,
                    'message' => 'Error al validar los campos',
                    'error' => $validate->errors()
                ], 400);
            }

            $users = \DB::table('users')
                ->where('email', $request->email)
                ->orWhere('username', $request->username)
                ->get();

            if(count($users) <= 0){
                $imagen64 = null;
                $mime = null;
                if(isset($request->image)){
                    $path = $request->file("image")->path();
                    $mime = \File::mimeType($path);
                    $img = \File::get($path);
                    $imagen64 = base64_encode($img);
                }
                $usuario = new User;
                $usuario->email = $request->email;
                $usuario->password = \Hash::make($request->password);
                $usuario->name = $request->name;
                $usuario->lastname = $request->lastname;
                $usuario->username = $request->username;
                $usuario->description = isset($request->description) ? $request->description : null;
                $usuario->image = $imagen64;
                $usuario->mimetype = $mime;
                $usuario->fecha_creacion = \Carbon\Carbon::now()->toDateTimeString();
                $usuario->save();

                return \Response::json([
                    'success' => true,
                    'message' => 'Usuario creado correctamente',
                    'usuarioId' => $usuario->id
                ], 200);
            }else{
                return \Response::json([
                    'success' => false,
                    'message' => 'Email o username ya registrado'
                ], 401);
            }
        } catch (\Exception $e) {
            \Log::info('error al registrar el usuario ' . $e);
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request){
        try {
            $validate = \Validator::make($request->all(),[
                'email' => 'required|email',
                'password' => 'required|string|min:4|max:50'
            ]);

            if($validate->fails()){
                return \Response::json([
                    'success' => false,
                    'message' => 'Error al validar los campos',
                    'error' => $validate->errors()
                ], 400);
            }

            $credentials = $request->only('email', 'password');
            if (!$token = JWTAuth::attempt($credentials)) {
                return \Response::json([
                    'status' => 'error',
                    'error' => 'invalid.credentials',
                    'msg' => 'Invalid Credentials.'
                ], 400);
            }
            return \Response::json([
                'status' => 'success',
                'token' => $token,
                'message' => 'Usuario loggueado correctamente'
            ], 200);
        } catch (\Exception $e) {
            \Log::info('error al iniciar sesion');
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function editProfile(Request $request){
        try {
            $validate = \Validator::make($request->all(),[
                'oldemail' => 'required|email',
                'email' => 'required|email',
                'password' => 'required|string|min:4|max:50',
                'name' => 'required|string|max:100',
                'lastname' => 'required|string|max:100',
                'username' => 'required|string|max:50',
                'description' => 'string|max:255'
            ]);

            if($validate->fails()){
                return \Response::json([
                    'success' => false,
                    'message' => 'Error al validar los campos',
                    'error' => $validate->errors()
                ], 400);
            }

            $user = \DB::table('users')
                ->where('email', $request->oldemail)
                ->first();

            if(!empty($user)){
                $imagen64 = null;
                $mime = null;
                if(isset($request->image)){
                    $path = $request->file("image")->path();
                    $mime = \File::mimeType($path);
                    $img = \File::get($path);
                    $imagen64 = base64_encode($img);
                }
                \DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'email' => $request->email,
                        'password' => \Hash::make($request->password),
                        'username' => $request->username,
                        'name' => $request->name,
                        'lastname' => $request->lastname,
                        'description' => $request->description,
                        'image' => $imagen64,
                        'mimetype' => $mime
                    ]);

                    return \Response::json([
                        'success' => true,
                        'message' => 'Usuario actualizado correctamente'
                    ], 200);
            }else{
                return \Response::json([
                    'success' => false,
                    'message' => 'Usuario no existente'
                ], 404);
            }
        } catch (\Exception $e) {
            \Log::info('error al editar informacion del usuario');
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showUsers($email){
        try {
            $users = \DB::table('users')   
                ->where('email', 'like', '%' . $email . '%')
                ->orWhere('username', 'like', '%' . $email . '%')
                ->select('id', 'email', 'username', 'name', 'lastname', 
                'description', 'image', 'mimetype','fecha_creacion')
                ->get();

            return \Response::json([
                'success' => true,
                'users' => $users
            ], 200);
        } catch (\Exception $e) {
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($idUser){
        try {
            $user = \DB::table('users')
                ->leftJoin('follow',function ($join){
                    $join->on('users.id','=','follow.idSeguidor')
                        ->where('follow.activo',1);
                })
                ->where('users.id', $idUser)
                ->select('users.*', 'follow.id as followId')
                ->get();

            return \Response::json([
                'success' => true,
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            \Log::info('Error al mostrar informacion de usuario ' . $e);
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function follow($idSeguidor){
        try {
            $user = \JWTAuth::parseToken()->authenticate();
            
            $follow = \DB::table('follow')
                ->where('idUser', $user->id)
                ->where('idSeguidor', $idSeguidor)
                ->get();
            
            if(count($follow) > 0){
                \DB::table('follow')
                    ->where('idUser', $user->id)
                    ->where('idSeguidor', $idSeguidor)
                    ->update([
                        'activo' => 0
                    ]);
                return \Response::json([
                    'success' => true,
                    'message' => 'Dio unfollow a este usuario'
                ], 200);
            }else{
                $follow = \DB::table('follow')
                    ->insertGetId([
                        'idUser' => $user->id,
                        'idSeguidor' => $idSeguidor
                    ]);
                return \Response::json([
                    'success' => true,
                    'message' => 'Dio follow a este usuario',
                    'idfollow' => $follow
                ], 200);
            }
        } catch (\Exception $e) {
            \Log::info('Error al seguir a otro usuario ' . $e);
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(){
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

}
