<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Auth Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Verify your email
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    We've sent a verification code to your email address.
                </p>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    {{ session('success') }}
                </div>
            @endif

            <form class="mt-8 space-y-6" action="{{ route('auth.verify') }}" method="POST">
                @csrf
                @if($errors->any())
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        {{ $errors->first() }}
                    </div>
                @endif

                <input type="hidden" name="username" value="{{ session('username') }}">

                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">Verification Code</label>
                    <input id="code" name="code" type="text" required maxlength="6"
                        class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-center text-lg tracking-widest"
                        placeholder="000000">
                    <p class="mt-2 text-sm text-gray-500 text-center">
                        Enter the 6-digit code from your email
                    </p>
                </div>

                <div>
                    <button type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        Verify Email
                    </button>
                </div>

                <div class="text-center">
                    <p class="text-sm text-gray-600">
                        Didn't receive the code?
                        <form method="POST" action="{{ route('auth.resend-verify') }}" class="inline">
                            @csrf
                            <input type="hidden" name="username" value="{{ session('username') }}">
                            <button type="submit"
                                class="font-medium text-indigo-600 hover:text-indigo-500">
                                Resend code
                            </button>
                        </form>
                    </p>
                </div>
            </form>

            <div class="text-center">
                <a href="{{ route('auth.login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                    Back to login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
