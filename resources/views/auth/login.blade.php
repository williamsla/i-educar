@extends('layout.public')

@section('content')
    <h2>Acesse sua conta</h2>
    @if(config('legacy.config.url_cadastro_usuario'))
        <div>Não possui uma conta? <a target="_blank" href="{{ config('legacy.config.url_cadastro_usuario') }}" rel="noopener">Crie sua conta agora</a>.</div>
    @endif

    <form action="{{ Asset::get('login') }}" method="post" id="form-login">

        <label for="login">Matrícula:</label>
        <input type="text" name="login" id="login">

        <label for="password">Senha:</label>
        <input type="password" name="password" id="password">
        <i class="fa fa-eye-slash" id="eye" onclick="return showPassword();" aria-hidden="true"></i>

        <button id="form-login-submit" type="submit" class="submit">Entrar</button>

        <div class="remember">
            <a href="{{ route('password.request') }}">Esqueceu sua senha?</a>
        </div>

    </form>

    <script>
        function showPassword() {
            var input = document.getElementById("password");
            var eye = document.getElementById("eye");

            if (input.type === "password") {
                input.type = "text";
                eye.classList.remove("fa-eye-slash");
                eye.classList.add("fa-eye");
            } else {
                input.type = "password";
                eye.classList.remove("fa-eye");
                eye.classList.add("fa-eye-slash");
            }
        }
    </script>

    <style>
        #eye {
            cursor: pointer;
            position: absolute;
            margin-top: -37px;
            margin-left: 335px;
            color: #188ad1;
        }
        @-moz-document url-prefix() {
            #eye {
                margin-top: 17px !important;
                margin-left: -25px !important;
            }
        }
    </style>

    @if (config('legacy.app.recaptcha_v3.public_key') && config('legacy.app.recaptcha_v3.private_key'))
        <script src="https://www.google.com/recaptcha/api.js?render={{config('legacy.app.recaptcha_v3.public_key')}}"></script>
        <script type="text/javascript" src="{{ Asset::get("/intranet/scripts/jquery/jquery-1.8.3.min.js") }} "></script>

        <script>
            let grecaptchaKey = "{{config('legacy.app.recaptcha_v3.public_key')}}";
            let form = $('#form-login');

            grecaptcha.ready(function() {
                form.submit(function(e) {
                    e.preventDefault();
                    grecaptcha.execute(grecaptchaKey, {action: 'submit'})
                        .then((token) => {
                            input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'grecaptcha';
                            input.value = token;

                            form.append(input);

                            $(this).unbind('submit').submit();
                        });
                });
            });
        </script>
    @endif
@endsection
