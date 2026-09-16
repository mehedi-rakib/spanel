<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="_token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ translate($role) }} | {{ translate('login')}}</title>
    <link rel="shortcut icon" href="{{getStorageImages(path: getWebConfig(name: 'company_fav_icon'), type:'backend-logo')}}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/google-fonts.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/vendor.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/vendor/icon-set/style.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/theme.minc619.css?v=1.0') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/style.css') }}">
    <link rel="stylesheet" href="{{ dynamicAsset(path: 'public/assets/back-end/css/toastr.css') }}">

    <style>
        :root {
            --c1: {{ $web_config['primary_color'] }};
        }

        body {
            min-height: 100vh;
            background: #f4f6f9;
        }

        .simple-login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .simple-login-card {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            padding: 32px 24px;
        }

        .simple-login-card .logo {
            display: block;
            text-align: center;
            margin-bottom: 24px;
        }

        .simple-login-card .logo img {
            max-width: 180px;
            max-height: 60px;
        }

        .simple-login-card h1 {
            font-size: 1.35rem;
            text-align: center;
            margin-bottom: 24px;
        }

        .simple-login-card .form-group {
            margin-bottom: 16px;
        }

        @media (max-width: 420px) {
            .simple-login-card {
                padding: 24px 16px;
            }
        }
    </style>

</head>

<body>
<main id="content" role="main">
    <div class="simple-login-wrap">
        <div class="simple-login-card">
            @php($eCommerceLogo = getWebConfig(name: 'company_web_logo'))
            <a class="logo" href="{{ url('/') }}">
                <img src="{{ getStorageImages(path: $eCommerceLogo, type:'backend-logo') }}" alt="Logo">
            </a>

            <h1>{{ translate($role) }} {{ translate('Login') }}</h1>

            <form action="{{route('login')}}" method="post" id="admin-login-form">
                @csrf
                <input type="hidden" name="role" id="role" value="{{ $role }}">

                <div class="form-group">
                    <label class="input-label" for="signingAdminEmail">{{ translate('your_email') }}</label>
                    <input type="email" class="form-control form-control-lg" name="email" id="signingAdminEmail"
                           tabindex="1" placeholder="email@address.com" required>
                </div>

                <div class="form-group">
                    <label class="input-label" for="signingAdminPassword">{{ translate('password') }}</label>
                    <div class="input-group input-group-merge">
                        <input type="password" class="js-toggle-password form-control form-control-lg"
                               name="password" id="signingAdminPassword" tabindex="2"
                               placeholder="{{ translate('8+_characters_required') }}" required
                               data-hs-toggle-password-options='{
                                    "target": "#changePassTarget",
                                    "defaultClass": "tio-hidden-outlined",
                                    "showClass": "tio-visible-outlined",
                                    "classChangeTarget": "#changePassIcon"
                               }'>
                        <div id="changePassTarget" class="input-group-append">
                            <a class="input-group-text" href="javascript:">
                                <i id="changePassIcon" class="tio-visible-outlined"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="termsCheckbox" name="remember">
                        <label class="custom-control-label text-muted" for="termsCheckbox">
                            {{translate('remember_me')}}
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-lg btn-block btn--primary">
                    {{ translate('sign_in')}}
                </button>
            </form>

            @if(env('APP_MODE')=='demo')
                <div class="card-footer mt-3">
                    <div class="row">
                        <div class="col-10">
                            <span id="admin-email" data-email="{{ \App\Enums\DemoConstant::ADMIN['email'] }}">{{translate('email')}} : {{ \App\Enums\DemoConstant::ADMIN['email'] }}</span><br>
                            <span id="admin-password" data-password="{{ \App\Enums\DemoConstant::ADMIN['password'] }}">{{translate('password')}} : {{ \App\Enums\DemoConstant::ADMIN['password'] }}</span>
                        </div>
                        <div class="col-2">
                            <button class="btn btn--primary" id="copyLoginInfo"><i class="tio-copy"></i></button>
                        </div>
                    </div>
                </div>
            @endif

            @if(SOFTWARE_VERSION)
                <div class="text-center mt-3">
                    <label class="badge badge-soft-success">
                        {{translate('software_version')}} : {{ SOFTWARE_VERSION }}
                    </label>
                </div>
            @endif
        </div>
    </div>
</main>

<span id="message-copied_success" data-text="{{ translate('copied_successfully') }}"></span>

<script src="{{dynamicAsset(path: 'public/assets/back-end/js/vendor.min.js')}}"></script>
<script src="{{dynamicAsset(path: 'public/assets/back-end/js/theme.min.js')}}"></script>
<script src="{{dynamicAsset(path: 'public/assets/back-end/js/toastr.js')}}"></script>
<script src="{{dynamicAsset(path: 'public/assets/back-end/js/admin/login.js')}}"></script>
{!! Toastr::message() !!}

@if ($errors->any())
    <script>
        "use strict";
        @foreach($errors->all() as $error)
        toastr.error('{{$error}}', Error, {
            CloseButton: true,
            ProgressBar: true
        });
        @endforeach
    </script>
@endif

</body>
</html>
