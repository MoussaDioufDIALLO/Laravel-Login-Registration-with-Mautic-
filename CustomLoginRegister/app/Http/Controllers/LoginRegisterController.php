<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;


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

        // Création du contact dans Mautic
        $response = $this->createMauticContact($request->name, $request->email, $request);

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

    //Mautic Function
    public function createMauticContact($name, $email, Request $request)
    {
        //Obtenir le jeton d'accès Mautic
        $accessToken = $this->getMauticAccessToken($request);

        if ($accessToken) {
            //Créer le contact dans Mautic
            $response = Http::withToken($accessToken)->post('http://localhost:8003/api/contacts/new', [
                'firstname' => $name,
                'email' => $email
            ]);

            if ($response->successful()) {
                return $response;
            } else {
                //Gérer l'erreur si la création du contact échoue
                return null;
            }
        } else {
            //Gérer l'erreur si la récupération du jeton d'accès échoue
            return null;
        }
    }

    //Méthode pour récupérer le jeton d'accès Mautic
    private function getMauticAccessToken(Request $request)
    {
        // Récupérez le jeton de rafraîchissement depuis la session
        $refreshToken = $request->session()->get('mautic_refresh_token');

        // Si le jeton de rafraîchissement n'est pas présent dans la session, utilisez la valeur codée en dur
        if (!$refreshToken) {
            $refreshToken = 'ZGNhZWYwZDViOWYzZjVmOTE4OTMyMDI3ZDBiZDE1MzUwZGQ2MGUwN2VlM2I1ZTVmZGE2ZWRkYTI0YmQyZmJhMA';
        }

        // Utilisez le jeton de rafraîchissement pour obtenir un nouveau jeton d'accès
        $response = Http::asForm()->post('http://localhost:8003/oauth/v2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => '2_5w7tbxbb2d4w4wwkog0gkw8c8w0so48ooko4gskkos440kocok',
            'client_secret' => '4ywmg1663b40kgksgsgcs08k8ok8kwwcg44kgs4sowssww4s84',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $accessToken = $data['access_token'];

            // Stockez le nouveau jeton de rafraîchissement dans la session
            $request->session()->put('mautic_refresh_token', $data['refresh_token']);

            return $accessToken;
        } else {
            // Gérer l'erreur si la récupération du jeton d'accès échoue
            return null;
        }
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
