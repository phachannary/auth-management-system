<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Auth Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">Auth Management System</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">
                        Welcome, {{ is_array($user) ? ($user['UserAttributes'][0]['Value'] ?? 'User') : $user->name }}
                    </span>
                    <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h2 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        User Profile Information
                    </h2>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500">User Details</h3>
                            <dl class="mt-2 space-y-1">
                                @if(is_array($user) && isset($user['UserAttributes']))
                                    @foreach($user['UserAttributes'] as $attribute)
                                        <div class="flex justify-between py-1">
                                            <dt class="text-sm font-medium text-gray-900">
                                                {{ ucfirst(str_replace('_', ' ', $attribute['Name'])) }}:
                                            </dt>
                                            <dd class="text-sm text-gray-500">
                                                {{ $attribute['Value'] }}
                                            </dd>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="flex justify-between py-1">
                                        <dt class="text-sm font-medium text-gray-900">Name:</dt>
                                        <dd class="text-sm text-gray-500">{{ $user->name }}</dd>
                                    </div>
                                    <div class="flex justify-between py-1">
                                        <dt class="text-sm font-medium text-gray-900">Email:</dt>
                                        <dd class="text-sm text-gray-500">{{ $user->email }}</dd>
                                    </div>
                                    <div class="flex justify-between py-1">
                                        <dt class="text-sm font-medium text-gray-900">Login Type:</dt>
                                        <dd class="text-sm text-gray-500">Google OAuth</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium text-gray-500">Account Status</h3>
                            <dl class="mt-2 space-y-1">
                                <div class="flex justify-between py-1">
                                    <dt class="text-sm font-medium text-gray-900">Status:</dt>
                                    <dd class="text-sm text-gray-500">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    </dd>
                                </div>
                                <div class="flex justify-between py-1">
                                    <dt class="text-sm font-medium text-gray-900">Username:</dt>
                                    <dd class="text-sm text-gray-500">{{ $user['Username'] }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h2 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Authentication Methods
                    </h2>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <svg class="h-8 w-8 text-indigo-600" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                                </svg>
                                <div class="ml-4">
                                    <h3 class="text-sm font-medium text-gray-900">AWS Cognito</h3>
                                    <p class="text-sm text-gray-500">Primary authentication method</p>
                                </div>
                            </div>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                Connected
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Last Login</dt>
                                    <dd class="text-lg font-medium text-gray-900">Just now</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Account Status</dt>
                                    <dd class="text-lg font-medium text-gray-900">Verified</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                                </svg>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Auth Method</dt>
                                    <dd class="text-lg font-medium text-gray-900">OAuth 2.0</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
