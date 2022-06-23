<?php

namespace App\Http\Controllers;

use App\Helpers\LDAPH\EasyLDAP;
use Illuminate\Http\Request;

class AccountController extends Controller
{

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
            return response()->json(['message' => 'Lo sentimos, los datos que ingresaste no coinciden con nuestros registros',403]);
        }

        //authenticate as admin and change the password
        $easyLDAP->authenticateAsAdmin();

        $filter = [
            'uid' => 'jimmy.garces',
        ];

        $result = $easyLDAP->getFirst($filter, $easyLDAP->roles[$role]);

        $modifiedAttributes = [
            'userPassword' => $easyLDAP::generateMD5Password($newPassword),
        ];

        $modify = $easyLDAP->modify($result['dn'], $modifiedAttributes);
        return response()->json(['message' => 'Tu contraseña ha sido cambiada exitosamente'],200);


    }

}
