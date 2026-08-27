<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Auth Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white shadow rounded-lg p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6">Privacy Policy</h1>
                
                <div class="prose prose-gray max-w-none">
                    <p class="text-gray-600 mb-4">Last updated: August 27, 2026</p>
                    
                    <h2 class="text-xl font-semibold text-gray-900 mt-6 mb-3">1. Information We Collect</h2>
                    <p class="text-gray-600 mb-4">We collect information you provide directly to us, including:</p>
                    <ul class="list-disc pl-6 text-gray-600 mb-4">
                        <li>Email address</li>
                        <li>Name</li>
                        <li>Authentication credentials (via AWS Cognito)</li>
                        <li>Social media profile information (when you sign in via Google or Facebook)</li>
                    </ul>
                    
                    <h2 class="text-xl font-semibold text-gray-900 mt-6 mb-3">2. How We Use Your Information</h2>
                    <p class="text-gray-600 mb-4">We use the information we collect to:</p>
                    <ul class="list-disc pl-6 text-gray-600 mb-4">
                        <li>Provide, maintain, and improve our services</li>
                        <li>Process authentication and authorization</li>
                        <li>Communicate with you about our services</li>
                        <li>Ensure security and prevent fraud</li>
                    </ul>
                    
                    <h2 class="text-xl font-semibold text-gray-900 mt-6 mb-3">3. Information Sharing</h2>
                    <p class="text-gray-600 mb-4">We do not sell your personal information. We may share your information with:</p>
                    <ul class="list-disc pl-6 text-gray-600 mb-4">
                        <li>AWS Cognito (for authentication services)</li>
                        <li>Google and Facebook (when you use their OAuth services)</li>
                        <li>Service providers who assist in operating our services</li>
                    </ul>
                    
                    <h2 class="text-xl font-semibold text-gray-900 mt-6 mb-3">4. Data Security</h2>
                    <p class="text-gray-600 mb-4">We implement appropriate security measures to protect your personal information, including encryption and secure authentication protocols provided by AWS Cognito.</p>
                    
                    <h2 class="text-xl font-semibold text-gray-900 mt-6 mb-3">5. Your Rights</h2>
                    <p class="text-gray-600 mb-4">You have the right to:</p>
                    <ul class="list-disc pl-6 text-gray-600 mb-4">
                        <li>Access your personal information</li>
                        <li>Correct inaccurate information</li>
                        <li>Request deletion of your personal information</li>
                        <li>Opt-out of certain data processing</li>
                    </ul>
                    
                    <h2 class="text-xl font-semibold text-gray-900 mt-6 mb-3">6. Contact Us</h2>
                    <p class="text-gray-600 mb-4">If you have questions about this Privacy Policy, please contact us at:</p>
                    <p class="text-gray-600">Email: support@authmanagement.com</p>
                </div>
                
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <a href="{{ route('home') }}" class="text-indigo-600 hover:text-indigo-500">← Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
