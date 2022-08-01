@component('mail::message')

Estimado(a) usuario

Para procesar tu solicitud de restauración de contraseña debes hacer clic en el botón de la parte inferior

Este mensaje fue generado automáticamente por la plataforma cuenta.unibague.edu.co. Te pedimos no responder al mismo.

Cordialmente,

Universidad de Ibagué
@component('mail::button', ['url' => 'http://localhost:8080/change-password?token='.$token])
Recuperar contraseña
@endcomponent

@endcomponent
