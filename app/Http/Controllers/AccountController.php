<?php

namespace App\Http\Controllers;

use App\Helpers\CurlCobain;
use App\Helpers\LDAPH\EasyLDAP;
use App\Http\Requests\VerifyTokenRequest;
use App\Models\PasswordChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{

    private $roles = [
        0 => '@estudiantesunibague.edu.co',
        1 => '@unibague.edu.co'
    ];

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \JsonException
     */
    public function rememberEmail(Request $request)
    {
        $this->validate($request, [
            'documentNumber' => 'required|string',
            'birthday' => 'required',
            'role' => 'required|numeric'
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
            //Check if the email is not found
            if ($answerAsObject['error'] === "Sin datos en correo") {
                return response()->json(['redirect' => 'https://forms.gle/7qC6tYM5ZCQBSxkw8'], 404);
            }
            $message = "<span>No encontramos una cuenta asociada a los datos ingresados. Por favor, verifica la información suministrada en el formulario. Si eres un egresado y no tuviste un correo institucional</span><a href='https://forms.gle/8d6McXtCTDSVvtfy5' style='color:blue'> lo puedes solicitar en este enlace.</a>";
            return response()->json(['message' => $message], 404);
        }

        //There is an anwers, lets get the email dependending on the role.
        $email = '';
        foreach ($answerAsObject as $possibleEmail) {
            if (str_contains($possibleEmail['dir_email'], $this->getEmailExtension($request->input('role')))) {
                $email = $possibleEmail['dir_email'];
            }
        }

        if ($email === '') {
            return response()->json(['message' => 'Ha ocurrido un error interno, por favor, comunicate con g3@unibague.edu.co y reporta el caso'], 500);
        }

        $user = explode('@', $email)[0];
        return response()->json(['message' => 'Tu usuario Unibagué es: ' . $user]);
    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \JsonException
     */
    public function changeAlternateEmail(Request $request)
    {
        try {
            $this->validate($request, [
                'user' => 'required',
                'role' => 'required',
                'password' => 'required',
                'alternateEmail' => 'required',
                'confirmAlternateEmail' => 'required|same:alternateEmail',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Por favor, revisa los datos del formulario'], 404);
        }
        //Get email by role
        $email = $request->input('user') . $this->getEmailExtension($request->input('role'));
        //Make request
        $curl = new CurlCobain('https://academia.unibague.edu.co/atlante/actualiza_alterno.php');
        $curl->setQueryParamsAsArray([
            'consulta' => 'Consultar',
            'account' => $email,
            'password' => $request->input('password'),
            'alterno' => $request->input('alternateEmail'),
            'token' => md5($email) . "YHyd?B'r8R7ejTRN"
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
    public function recoverPassword(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $this->validate($request, [
                'user' => 'required',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Por favor, revisa los datos del formulario'], 404);
        }
        //Get email by role
        $userEmail = $request->input('user') . $this->getEmailExtension($request->input('role'));

        //Make request
        $curl = new CurlCobain('https://academia.unibague.edu.co/atlante/recordar_alterno.php');
        $curl->setQueryParamsAsArray([
            'consulta' => 'Consultar',
            'correo' => $userEmail,
        ]);

        $answer = $curl->makeRequest();
        $answerAsObject = json_decode($answer, true, 512, JSON_THROW_ON_ERROR);

        if (isset($answerAsObject['error'])) {
            return response()->json(['message' => $answerAsObject['error']], 404);
        }
        $email = $answerAsObject['correo_altero'];
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
            'email' => $userEmail,
            'alternateEmail' => $email,
            'isActive' => true,
            'token' => $token
        ]);

        try {
            Mail::to($email)->send(new \App\Mail\RecoverPassword($token));
        } catch (\Exception $e) {
            response()->json(['message' => 'Error enviando email :' . $e->getMessage()]);
        }

        return response()->json(['message' => 'Hemos enviado un enlace de recuperación de contraseña a tu correo alterno registrado: ' . "$user@$domain"]);
    }

    public function getEmailExtension(int $role): string
    {
        return $this->roles[$role];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function verifyToken(Request $request)
    {
        $this->validate($request, [
            'token' => 'required'
        ]);

        $changePasswordRequest = PasswordChangeRequest::where('token', '=', $request->input('token'))->latest()->first();
        if (!$changePasswordRequest) {
            return response('', 404);
        }
        if ($changePasswordRequest->isActive === 1) {
            return response('', 200);
        }
        return response('', 404);

    }

    public function changePassword(Request $request)
    {
        $this->validate($request, [
            'user' => 'required_without:token|string',
            'password' => 'required_without:token',
            'newPassword' => 'required',
            'confirmNewPassword' => 'required|same:newPassword',
            'role' => 'required|numeric'
        ]);
        //prepare LDAP and parse request inputs
        $easyLDAP = new EasyLDAP(false);

        $newPassword = $request->input('newPassword');
        $role = $request->input('role');

        if ($request->input('token') === null) {
            $user = $request->input('user');
            $password = $request->input('password');

            //Check if the credentials match
            try {
                $easyLDAP->authenticate($user, $password, $role);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Lo sentimos, los datos que ingresaste no coinciden con nuestros registros'], 403);
            }

        } //The token is not null, verifies if exist and is valid.
        else {
            $token = $request->input('token');
            //Find it
            $passwordChangeRequest = PasswordChangeRequest::where('token', '=', $token)->first();
            //not found
            if (!$passwordChangeRequest) {
                return response()->json(['message' => 'El código que ingresaste no es válido'], 404);
            }
            //The code is not active
            if ($passwordChangeRequest->isActive === 0) {
                return response()->json(['message' => 'El código que ingresaste ya ha expirado o ha sido usado previamente'], 400);
            }
            //The code is active, proceed with password change
            $user = explode('@', $passwordChangeRequest->email)[0];
            $passwordChangeRequest->isActive = 0;
            $passwordChangeRequest->save();
        }

        //authenticate as admin and change the password
        $easyLDAP->authenticateAsAdmin();

        $filter = ['uid' => $user,];

        $result = $easyLDAP->getFirst($filter, $easyLDAP->roles[$role]);

        $modifiedAttributes = ['userPassword' => $easyLDAP::generateMD5Password($newPassword),];

        $modify = $easyLDAP->modify($result['dn'], $modifiedAttributes);
        if ($modify === true) {
            return response()->json(['message' => 'Tu contraseña ha sido cambiada exitosamente'], 200);
        }
        return response()->json(['message' => 'Ha ocurrido un error interno, por favor intentalo mas tarde'], 500);
    }

}
