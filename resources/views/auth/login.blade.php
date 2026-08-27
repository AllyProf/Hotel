@extends('layouts.auth')

@section('title', 'Login')

@section('content')
  <section class="material-half-bg">
    <div class="cover"></div>
  </section>
  <section class="login-content">
    <div class="logo">
      <h1>{{ config('app.name', 'Hotel SaaS') }}</h1>
    </div>
    <div class="login-box">
      <form class="login-form" action="{{ route('login') }}" method="POST">
        @csrf
        <h3 class="login-head"><i class="fa fa-lg fa-fw fa-user"></i>SIGN IN</h3>

        @if($errors->any() && ! $errors->has('email') && ! $errors->has('password'))
          <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif


        <div class="form-group">
          <label class="control-label">EMAIL</label>
          <input class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" placeholder="Email" autofocus required>
          @error('email')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </div>
        <div class="form-group">
          <label class="control-label">PASSWORD</label>
          <input class="form-control @error('password') is-invalid @enderror" type="password" name="password" placeholder="Password" required>
          @error('password')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </div>
        <div class="form-group">
          <div class="utility">
            <div class="animated-checkbox">
              <label>
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}><span class="label-text">Stay Signed in</span>
              </label>
            </div>
          </div>
        </div>
        <div class="form-group btn-container">
          <button class="btn btn-primary btn-block" type="submit">
            <i class="fa fa-sign-in fa-lg fa-fw"></i>SIGN IN
          </button>
        </div>
      </form>
    </div>
  </section>
@endsection
