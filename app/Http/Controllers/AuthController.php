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
                'password' => 'required|string|min:4|max:50'
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
                ->get();

            if(count($users) <= 0){
                $usuario = new User;
                $usuario->email = $request->email;
                $usuario->password = \Hash::make($request->password);
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
                    'message' => 'Usuario ya registrado'
                ], 401);
            }
        } catch (\Exception $e) {
            \Log::info('error al registrar el usuario');
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
}
