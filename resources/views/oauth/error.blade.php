<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorization Error</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-6">
            <h2 class="text-center text-3xl font-extrabold text-gray-900">
                Authorization request rejected
            </h2>

            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                <p class="font-semibold">{{ $error }}</p>
                <p class="mt-1 text-sm">{{ $error_description }}</p>
            </div>

            <p class="text-center text-sm text-gray-600">
                The application that sent you here is misconfigured. Please contact its administrator.
            </p>

            <p class="text-center text-sm">
                <a href="{{ route('home') }}" class="font-medium text-indigo-600 hover:text-indigo-500">Return to home</a>
            </p>
        </div>
    </div>
</body>
</html>
