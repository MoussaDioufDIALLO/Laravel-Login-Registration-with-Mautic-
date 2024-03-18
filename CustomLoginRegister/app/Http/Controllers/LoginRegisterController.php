<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log; 


class LoginRegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->except([
            'logout', 'dashboard'
        ]);
    }
    public function register()
    {
        return view('auth.register');
    }
   // App\Http\Controllers\LoginRegisterController
   public function store(Request $request)
   {
       // Validation des données du formulaire
       $request->validate([
           'name' => 'required|string|max:250',
           'email' => 'required|email|max:250|unique:users',
           'password' => 'required|min:8|confirmed'
       ]);
   
       // Création de l'utilisateur dans Laravel
       $user = User::create([
           'name' => $request->name,
           'email' => $request->email,
           'password' => Hash::make($request->password)
       ]);
   
       // Obtention du jeton d'accès Mautic
       $accessToken = $this->getMauticAccessToken($request);
       Log::info('On obtient ce token ' . $accessToken);
   
       // Création du contact dans Mautic
       $response = $this->createMauticContact(
           $request->name,
           $request->email,
           $accessToken,
           env('MAUTIC_BASE_URL')
       );
   
       if ($response && $response->successful()) {
           // Redirection vers le tableau de bord avec un message de succès
           return redirect()->route('dashboard')->withSuccess('You have successfully registered and logged in!');
       } else {
           // En cas d'échec, supprimez l'utilisateur créé précédemment dans Laravel
           $user->delete();
           // Redirection vers la page d'inscription avec un message d'erreur
           return back()->withErrors(['registration_error' => 'An error occurred during registration. Please try again.']);
       }
   }
   
   // Méthode pour récupérer le jeton d'accès Mautic
   private function getMauticAccessToken(Request $request)
   {
       $response = Http::asForm()->post(env('MAUTIC_BASE_URL') . '/oauth/v2/token', [
           'grant_type' => 'client_credentials',
           'client_id' => env('MAUTIC_CLIENT_ID'),
           'client_secret' => env('MAUTIC_CLIENT_SECRET'),
           'redirect_uri' => env('MAUTIC_REDIRECT_URI')
       ]);
   
       if ($response->successful()) {
           return $response->json()['access_token'];
       } else {
           return null;
       }
   }
   
   // Méthode pour créer un contact dans Mautic
   private function createMauticContact($name, $email, $accessToken, $mauticApiUrl)
   {
       $response = Http::withToken($accessToken)->post($mauticApiUrl . '/api/contacts/new', [
           'firstname' => $name,
           'email' => $email
       ]);
       return $response;
   }

    public function login()
    {
        return view('auth.login');
    }
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->route('dashboard')
                ->withSuccess('You have successfully logged in!');
        }

        return back()->withErrors([
            'email' => 'Your provided credentials do not match in our records.',
        ])->onlyInput('email');
    }
    public function dashboard()
    {
        if (Auth::check()) {
            return view('auth.dashboard');
        }
        return redirect()->route('login')->withErrors([
            'email' => 'Please login to access the dashboard',
        ])->onlyInput('email');
    }
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/')->withSuccess('You have logged out successfully');
    }
}
