<?php

namespace App\Http\Controllers;

use App\Helpers\CurlCobain;
use App\Helpers\LDAPH\EasyLDAP;
use App\Models\PasswordChangeRequest;
use Illuminate\Http\Request;

class AccountController extends Controller
{

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \JsonException
     */
    public function rememberEmail(Request $request)
    {
        $this->validate($request, [
            'documentNumber' => 'required|string',
            'birthday' => 'required',
        ]);

        [$year, $month, $day] = explode('-', $request->birthday);

        $curl = new CurlCobain('https://academia.unibague.edu.co/atlante/recordar_usuario.php');
        $curl->setQueryParamsAsArray([
            'consulta' => 'Consultar',
            'documento' => $request->documentNumber,
            'dia' => $day,
            'mes' => $month,
            'ano' => $year,
        ]);

        $answer = $curl->makeRequest();
        $answerAsObject = json_decode($answer, true, 512, JSON_THROW_ON_ERROR);

        if (isset($answerAsObject['error'])) {
            return response()->json(['message' => $answerAsObject['error']], 404);
        }

        return response()->json(['message' => 'Estimado usuario, tu correo electrónico Unibagué es: ' . $answerAsObject[0]['cod_usuario']]);
    }


    public function changeAlternateEmail(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required',
            'alternateEmail' => 'required',
            'confirmAlternateEmail' => 'required|same:alternateEmail',
        ]);


        $curl = new CurlCobain('https://academia.unibague.edu.co/atlante/actualiza_alterno.php');
        $curl->setQueryParamsAsArray([
            'consulta' => 'Consultar',
            'account' => $request->input('email'),
            'password' => $request->input('password'),
            'alterno' => $request->input('alternateEmail'),
            'token' => md5($request->input('email')) . "YHyd?B'r8R7ejTRN"
        ]);

        $answer = $curl->makeRequest();
        $answerAsObject = json_decode($answer, true, 512, JSON_THROW_ON_ERROR);

        if (isset($answerAsObject['error'])) {
            return response()->json(['message' => $answerAsObject['error']], 404);
        }

        return response()->json(['message' => 'Estimado usuario, tu correo alterno ha sido actualizado exitosamente']);
    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \JsonException
     * @throws \Exception
     */
    public function recoverPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
        ]);


        $curl = new CurlCobain('https://academia.unibague.edu.co/atlante/recordar_alterno.php');
        $curl->setQueryParamsAsArray([
            'consulta' => 'Consultar',
            'correo' => $request->email,
        ]);

        $answer = $curl->makeRequest();
        $answerAsObject = json_decode($answer, true, 512, JSON_THROW_ON_ERROR);

        if (isset($answerAsObject['error'])) {
            return response()->json(['message' => $answerAsObject['error']], 404);
        }
        $email = $answerAsObject[0]['correo_altero'];
        [$user, $domain] = explode('@', $email);

        $userLength = strlen($user);
        if (strlen($user) <= 8) {
            $hidden = str_repeat('*', $userLength - 2);
            $user = substr_replace($user, $hidden, 1, $userLength - 2);
        } else {
            $hidden = str_repeat('*', $userLength - 4);
            $user = substr_replace($user, $hidden, 2, $userLength - 4);
        }

        $token = bin2hex(random_bytes(30));

        PasswordChangeRequest::create([
            'email' => $request->input('email'),
            'alternateEmail' => $email,
            'isActive' => true,
            'token' => $token
        ]);

        \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\RecoverPassword($token));

        return response()->json(['message' => 'Hemos enviado un enlace de recuperación de contraseña a tu correo alterno registrado: ' . "$user@$domain"]);
    }

    public function changePassword(Request $request)
    {
        $this->validate($request, [
            'user' => 'required|string',
            'password' => 'required',
            'newPassword' => 'required',
            'confirmNewPassword' => 'required|same:newPassword',
            'role' => 'required|numeric'
        ]);

        $easyLDAP = new EasyLDAP(false);

        //Check if the credentials match
        $user = $request->input('user');
        $password = $request->input('password');
        $newPassword = $request->input('newPassword');
        $role = $request->input('role');
        try {
            $easyLDAP->authenticate($user, $password, $role);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lo sentimos, los datos que ingresaste no coinciden con nuestros registros', 403]);
        }

        //authenticate as admin and change the password
        $easyLDAP->authenticateAsAdmin();

        $filter = [
            'uid' => $user,
        ];

        $result = $easyLDAP->getFirst($filter, $easyLDAP->roles[$role]);

        $modifiedAttributes = [
            'userPassword' => $easyLDAP::generateMD5Password($newPassword),
        ];

        $modify = $easyLDAP->modify($result['dn'], $modifiedAttributes);
        if ($modify === true) {
            return response()->json(['message' => 'Tu contraseña ha sido cambiada exitosamente'], 200);
        }

        return response()->json(['message' => 'Ha ocurrido un error interno, por favor intentalo mas tarde'], 500);

    }

}
