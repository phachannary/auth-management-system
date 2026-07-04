<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Auth Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                @if($alreadyConfirmed)
                    {{-- Success state for already-confirmed users --}}
                    <div class="flex justify-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                    <h2 class="mt-2 text-center text-3xl font-extrabold text-gray-900">
                        Email Verified
                    </h2>
                    <p class="mt-2 text-center text-sm text-gray-600">
                        Your email <span class="font-semibold">{{ $email }}</span> is already verified.<br>
                        Click below to access your dashboard.
                    </p>
                @else
                    {{-- Pending OTP verification state --}}
                    <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                        Verify Your Email
                    </h2>
                    <p class="mt-2 text-center text-sm text-gray-600">
                        We've sent a 6-digit code to:<br>
                        <span class="font-semibold">{{ $email }}</span>
                    </p>
                @endif
            </div>

            @if(session('info'))
                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">{{ session('info') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(session('warning'))
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">{{ session('warning') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">{{ $errors->first() }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <form class="mt-8 space-y-6" action="{{ route('auth.verification.verify') }}" method="POST">
                @csrf
                <input type="hidden" name="username" value="{{ $username }}">
                <input type="hidden" name="otp_session_id" value="{{ $otpSessionId }}">

                @if($alreadyConfirmed)
                    {{-- Cognito user already confirmed: no new OTP can be sent, just confirm identity --}}
                    <input type="hidden" name="code" value="000000">
                    <div class="bg-green-50 border border-green-200 rounded-md p-4 text-sm text-green-700">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span><strong>{{ $email }}</strong> is verified and ready to use.</span>
                        </div>
                    </div>
                @else
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700">
                            Verification Code
                        </label>
                        <div class="mt-1">
                            <input id="code" name="code" type="text" required
                                   class="appearance-none block w-full px-3 py-3 border border-gray-300 rounded-md placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-center text-xl font-mono"
                                   placeholder="000000"
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   autocomplete="one-time-code">
                            <p class="mt-2 text-sm text-gray-600">Enter the 6-digit code sent to: <strong>{{ $email }}</strong></p>
                            <p id="otp-timer" class="mt-2 text-sm font-medium {{ $isExpired ? 'text-red-600' : 'text-indigo-600' }}"></p>
                        </div>
                    </div>
                @endif

                <div>
                    <button id="verify-button" type="submit"
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white {{ $alreadyConfirmed ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500' }} focus:outline-none focus:ring-2 focus:ring-offset-2 transition duration-200">
                        @if($alreadyConfirmed)
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        @else
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        @endif
                        @if($alreadyConfirmed) Continue to Dashboard @else Verify Email @endif
                    </button>
                </div>
            </form>

            <div id="resend-section" class="text-center space-y-2 mt-6 {{ $isExpired ? '' : 'hidden' }}">
                <p class="text-sm text-gray-600">
                    Code expired or didn't receive it? Send a fresh code and use the newest email only.
                </p>
                <form action="{{ route('auth.verification.resend') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2 rounded-md text-sm text-white bg-indigo-600 hover:bg-indigo-700 font-medium transition duration-200">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Resend New Code
                    </button>
                </form>
            </div>

            <div class="text-center">
                <a href="{{ route('auth.login') }}"
                   class="text-sm text-gray-600 hover:text-gray-500">
                    ← Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        const alreadyConfirmed = @json($alreadyConfirmed);
        const expiresAt = @json($expiresAtIso) ? new Date(@json($expiresAtIso)).getTime() : null;
        const timer = document.getElementById('otp-timer');
        const codeInput = document.getElementById('code');
        const verifyButton = document.getElementById('verify-button');
        const resendSection = document.getElementById('resend-section');

        // If already confirmed in Cognito, don't run countdown at all
        if (alreadyConfirmed) {
            if (timer) {
                timer.textContent = '';
            }
            if (resendSection) {
                resendSection.classList.add('hidden');
            }
        } else {
            function setExpiredState() {
                if (timer) {
                    timer.textContent = 'Code expired. Please resend a new code.';
                    timer.classList.remove('text-indigo-600');
                    timer.classList.add('text-red-600');
                }
                if (verifyButton) {
                    verifyButton.textContent = 'Code Expired - Resend New Code';
                    verifyButton.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                    verifyButton.classList.add('bg-gray-500');
                }
                if (resendSection) {
                    resendSection.classList.remove('hidden');
                }
            }

            function updateTimer() {
                if (!expiresAt) {
                    setExpiredState();
                    return;
                }

                const remaining = expiresAt - Date.now();

                if (remaining <= 0) {
                    setExpiredState();
                    return;
                }

                const minutes = Math.floor(remaining / 60000);
                const seconds = Math.floor((remaining % 60000) / 1000);
                if (timer) {
                    timer.textContent = `Code expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
                }
            }

            updateTimer();
            setInterval(updateTimer, 1000);
        }

        // Auto-focus and format the verification code input (only for normal OTP flow)
        if (codeInput && !alreadyConfirmed) {
            codeInput.addEventListener('input', function(e) {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');

                // Removed auto-submit - user must click verify button
            });
        }

        // Focus the input on page load (only if input exists and not already confirmed)
        if (codeInput && !codeInput.disabled && !alreadyConfirmed) {
            codeInput.focus();
        }
    </script>
</body>
</html>
