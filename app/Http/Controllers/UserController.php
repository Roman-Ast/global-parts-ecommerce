<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('user.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'max:100'],
            'email' => ['required', 'email', 'max:100', 'unique:users'],
            'phone' => ['required','max:16'],
            'password' => ['required, confirmed']
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'user_role' => 'common'
        ]);
        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
        /*return redirect()->route('login')
            ->with('message', 'Вы успешно зарегестрировались!')
            ->with('class', 'alert-success');*/
    }

    public function dashboard()
    {
        return view('user.dashboard');
    }

    public function login(User $user)
    {
        return view('user.login');
    }

    public function logout(Request $request) {
        Auth::logout();
        return redirect('/');
      }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }
}
