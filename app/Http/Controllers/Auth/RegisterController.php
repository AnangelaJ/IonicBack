<?php

namespace App\Http\Controllers\Auth;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'email' => ['required', 'string', 'email', 'max:50'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    public function create(Request $request)
    {
        try {
            return $request;
            $user = \DB::table('users')
                ->where('email', $request->email)
                ->get();
            if(count($user) <= 0){
                $usuario = new User;
                $usuario->email = $request->email;
                $usuario->password = Hash::make($data['password']);
                $usuario->fecha_creacion = \Carbon\Carbon::now()->toDateTimeString;
                $usuario->save();

                return \Response::json([
                    'success' => true,
                    'usuario' => $usuario->id
                ], 200);
            }else{
                return \Response::json([
                    'success' => false,
                    'message' => 'Usuario registrado'
                ], 401);
            }
        } catch (\Exception $e) {
            \Log::info('error al crear usuario');
            return \Response::json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
