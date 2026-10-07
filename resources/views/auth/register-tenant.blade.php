@php
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
    $setting = fn (string $key, string $default = '') => (string) ($registrationSettings[$key] ?? $default);
    $assetUrl = function (?string $path) {
        if (! $path) {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset(ltrim($path, '/'));
    };
    $defaultSystemLogo = 'uploads/platform/app-logo-20260505221831.png';
    $registrationLogo = $assetUrl($setting('registration_logo'))
        ?: ($platformLogo ?? null)
        ?: (\Illuminate\Support\Facades\File::exists(public_path($defaultSystemLogo)) ? asset($defaultSystemLogo) : null);
    $backgroundImage = $assetUrl($setting('registration_background_image'));
    $primary = $setting('registration_primary_color', '#2563eb');
    $accent = $setting('registration_accent_color', '#16a34a');
    $background = $setting('registration_background_color', '#f8fafc');
    $errorKeys = $errors->keys();
    $initialStep = collect($errorKeys)->contains(fn ($key) => in_array($key, [
        'address', 'billing_address', 'tax_identifier', 'company_logo',
        'government_id_documents', 'tax_documents', 'business_documents',
    ], true) || str_contains($key, 'documents')) ? 2 : (collect($errorKeys)->contains(fn ($key) => in_array($key, [
        'plan_id', 'billing_cycle', 'billing_method', 'website_development_requested',
    ], true)) ? 3 : (collect($errorKeys)->contains(fn ($key) => $key === 'terms') ? 4 : 1));
@endphp
<!DOCTYPE html>
<html lang="en" class="registration-loading">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $setting('registration_platform_title', 'Freitora Courier Platform') }} - Registration</title>
    @if($platformFavicon ?? null)<link rel="icon" href="{{ $platformFavicon }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --registration-primary: {{ $primary }};
            --registration-accent: {{ $accent }};
            --registration-background: {{ $background }};
        }
        * { box-sizing: border-box; }
        html.registration-loading body { visibility: hidden; }
        body { margin: 0; min-height: 100vh; }
        body > .min-h-screen {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2.5rem 1rem;
        }
        header.w-full, main.w-full { width: 100%; max-width: 56rem; }
        header img { display: block; max-width: 100%; max-height: 7rem; width: auto; height: auto; object-fit: contain; }
        main { background: #fff; border: 1px solid #f1f5f9; border-radius: 1rem; overflow: hidden; }
        #onboardingForm { padding: 2.5rem; }
        .step-container:not(.step-active) { display: none; }
        body {
            background-color: var(--registration-background);
            @if($backgroundImage) background-image: linear-gradient(rgba(248,250,252,.94), rgba(248,250,252,.94)), url('{{ $backgroundImage }}'); @endif
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: Inter, sans-serif;
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .required::after { content: "*"; color: #ef4444; margin-left: .25rem; }
        .step-container { transition: opacity .4s ease, transform .4s ease; }
        .step-hidden { opacity: 0; transform: translateY(15px); pointer-events: none; position: absolute; visibility: hidden; height: 0; overflow: hidden; }
        .step-active { opacity: 1; transform: translateY(0); position: relative; visibility: visible; height: auto; }
        .bg-registration-primary { background-color: var(--registration-primary) !important; }
        .text-registration-primary { color: var(--registration-primary) !important; }
        .border-registration-primary { border-color: var(--registration-primary) !important; }
        .registration-soft { background: color-mix(in srgb, var(--registration-primary) 8%, white); border-color: color-mix(in srgb, var(--registration-primary) 22%, white); }
        .registration-input:focus { border-color: var(--registration-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--registration-primary) 18%, transparent); outline: none; }
        .plan-radio:checked + div { border-color: var(--registration-primary); background-color: color-mix(in srgb, var(--registration-primary) 7%, white); box-shadow: 0 0 0 2px color-mix(in srgb, var(--registration-primary) 20%, transparent); }
        .plan-radio:checked + div .check-icon { opacity: 1; transform: scale(1); color: var(--registration-primary); }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .plan-card-wrapper { flex: 0 0 auto; width: 85%; max-width: 320px; }
        input[type="file"]::file-selector-button { border: 0; background: #e2e8f0; padding: 6px 12px; border-radius: 6px; color: #475569; cursor: pointer; font-size: .875rem; font-weight: 500; margin-right: 12px; }
        input[type="file"]::file-selector-button:hover { background: #cbd5e1; color: #1e293b; }
        .field-error { color: #dc2626; font-size: .75rem; margin-top: .35rem; }
        .invalid-field { border-color: #ef4444 !important; }
        .completed-step { background-color: var(--registration-accent) !important; }
        @media (max-width: 640px) {
            .registration-step-label { max-width: 68px; line-height: 1.15; text-align: center; }
            #onboardingForm { padding: 1.5rem; }
        }
    </style>
    <script>
        window.addEventListener('load', function () {
            requestAnimationFrame(function () {
                document.documentElement.classList.remove('registration-loading');
            });
        });
        setTimeout(function () {
            document.documentElement.classList.remove('registration-loading');
        }, 2500);
    </script>
</head>
<body class="text-gray-800 antialiased selection:bg-blue-500 selection:text-white">
    <div class="min-h-screen flex flex-col items-center py-10 px-4 sm:px-6 lg:px-8">
        <header class="w-full max-w-4xl mb-8 flex flex-col items-center text-center relative">
            <div class="absolute top-0 right-0 hidden sm:flex text-sm text-gray-500 items-center bg-white px-3 py-1.5 rounded-full border border-gray-200 shadow-sm">
                <svg class="w-4 h-4 mr-1" style="color: var(--registration-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                {{ $setting('registration_secure_label', 'Secure') }}
            </div>
            <div class="mx-auto flex justify-center mb-4 w-full max-w-[320px] h-28 items-center overflow-hidden">
                @if($registrationLogo)
                    <img src="{{ $registrationLogo }}" alt="{{ $setting('registration_platform_title', 'Courier Platform') }}" class="max-h-full max-w-full object-contain drop-shadow-sm">
                @else
                    <div class="w-20 h-20 rounded-2xl bg-registration-primary text-white flex items-center justify-center shadow-lg">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM7 17a2 2 0 11-4 0m17 0a2 2 0 11-4 0"></path></svg>
                    </div>
                @endif
            </div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ $setting('registration_platform_title', 'Freitora Courier Platform') }}</h1>
            <p class="text-md text-gray-500 mt-2">{{ $setting('registration_portal_subtitle', 'Partner Onboarding Portal') }}</p>
        </header>

        <main class="w-full max-w-4xl bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-100 px-5 sm:px-8 py-4">
                <nav aria-label="Registration progress">
                    <ol class="flex items-start justify-between w-full relative">
                        <li class="absolute top-4 left-4 right-4 h-0.5 bg-gray-200"></li>
                        <li class="absolute top-4 left-4 h-0.5 bg-registration-primary transition-all duration-500" id="progress-bar-fill"></li>
                        @foreach([
                            1 => $setting('registration_step_business', 'Business Info'),
                            2 => $setting('registration_step_documents', 'Documents'),
                            3 => $setting('registration_step_plan', 'Plan & Billing'),
                            4 => $setting('registration_step_review', 'Review'),
                        ] as $number => $label)
                            <li class="relative z-10 flex flex-col items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-semibold text-sm ring-4 ring-white transition-colors duration-300" id="step-indicator-{{ $number }}">{{ $number }}</div>
                                <span class="registration-step-label mt-2 text-xs font-medium transition-colors" id="step-label-{{ $number }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            </div>

            <form id="onboardingForm" method="POST" action="{{ route('tenant.register.store') }}" enctype="multipart/form-data" class="p-6 sm:p-10" novalidate>
                @csrf
                @if($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <div class="font-bold mb-1">Registration needs attention</div>
                        {{ $errors->first() }}
                        @if(collect($errorKeys)->contains(fn ($key) => str_contains($key, 'documents') || $key === 'company_logo'))
                            <div class="mt-1">For security, please select your upload files again.</div>
                        @endif
                    </div>
                @endif

                <section id="step-1" class="step-container">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $setting('registration_business_title', 'Business Information') }}</h2>
                        <p class="text-gray-500 text-sm mt-1">{{ $setting('registration_business_intro', "Let's start with the basic details of your courier company.") }}</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 required">Registered Business Name</label>
                            <input name="company_name" value="{{ old('company_name') }}" required class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm @error('company_name') invalid-field @enderror">
                            @error('company_name')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 required">Legal Representative / Owner</label>
                            <input name="legal_representative" value="{{ old('legal_representative') }}" required placeholder="Full Name" class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm @error('legal_representative') invalid-field @enderror">
                            @error('legal_representative')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 required">Preferred Company Code</label>
                            <input name="company_code" value="{{ old('company_code') }}" required maxlength="20" pattern="[A-Za-z0-9_-]+" placeholder="e.g., OTISI" class="registration-input uppercase mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm @error('company_code') invalid-field @enderror">
                            <p class="text-xs text-gray-500 mt-1">Short prefix for your workspace and tracking numbers.</p>
                            @error('company_code')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 required">Email Address</label>
                            <input type="email" name="email" value="{{ old('email') }}" required placeholder="admin@yourcompany.com" class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm @error('email') invalid-field @enderror">
                            @error('email')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Website URL (If applicable)</label>
                            <input type="url" name="website_url" value="{{ old('website_url') }}" placeholder="https://www.yourcompany.com" class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm @error('website_url') invalid-field @enderror">
                            @error('website_url')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 required">Contact Number</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm @error('phone') invalid-field @enderror">
                            @error('phone')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 required">WhatsApp Number</label>
                            <input type="tel" name="whatsapp_number" value="{{ old('whatsapp_number') }}" required class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm @error('whatsapp_number') invalid-field @enderror">
                            @error('whatsapp_number')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="mt-7 border-t border-gray-200 pt-6">
                        <h3 class="text-md font-semibold text-gray-800 mb-4">Administrator Login</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 required">Admin Name</label>
                                <input name="admin_name" value="{{ old('admin_name') }}" required class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 required">Password</label>
                                <input type="password" name="password" minlength="8" required class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 required">Confirm Password</label>
                                <input type="password" name="password_confirmation" minlength="8" required class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm">
                            </div>
                        </div>
                    </div>
                </section>

                <section id="step-2" class="step-container">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $setting('registration_documents_title', 'Address & Documents') }}</h2>
                        <p class="text-gray-500 text-sm mt-1">{{ $setting('registration_documents_intro', 'Provide operational locations and verify your business identity.') }}</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 required">Business Address</label>
                            <textarea id="business_address" name="address" rows="3" required class="registration-input mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg sm:text-sm">{{ old('address') }}</textarea>
                        </div>
                        <div>
                            <div class="flex justify-between items-center mb-1 gap-3">
                                <label class="block text-sm font-medium text-gray-700 required">Billing Address</label>
                                <label class="flex items-center text-xs text-gray-600 cursor-pointer whitespace-nowrap">
                                    <input id="same_address" type="checkbox" class="h-4 w-4 mr-2 rounded border-gray-300">
                                    Same as Business
                                </label>
                            </div>
                            <textarea id="billing_address" name="billing_address" rows="3" required class="registration-input block w-full px-4 py-2 border border-gray-300 rounded-lg sm:text-sm">{{ old('billing_address') }}</textarea>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <div class="md:w-1/2 mb-4">
                            <label class="block text-sm font-medium text-gray-700 required">TRN / EIN Number</label>
                            <input name="tax_identifier" value="{{ old('tax_identifier') }}" required placeholder="Tax Registration Number" class="registration-input mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg sm:text-sm">
                        </div>
                        <h3 class="text-md font-semibold text-gray-800 mb-3">Required Documents</h3>
                        <p class="text-xs text-gray-500 mb-4">Upload clear copies. Maximum 5MB per file. Accepted formats: PDF, JPG, and PNG.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            @foreach([
                                ['company_logo', 'Company Logo', 'Your brand identity.', '.jpg,.jpeg,.png,.webp,.svg', false],
                                ['government_id_documents[]', 'Government ID', 'ID of legal representative.', '.pdf,.jpg,.jpeg,.png', true],
                                ['tax_documents[]', 'TRN / EIN Document', 'Official tax document.', '.pdf,.jpg,.jpeg,.png', true],
                                ['business_documents[]', 'Business Reg.', 'Certificate of incorporation.', '.pdf,.jpg,.jpeg,.png', true],
                            ] as [$name, $label, $help, $accept, $required])
                                <div class="border border-gray-300 rounded-lg p-4 bg-gray-50 relative">
                                    <label class="block text-sm font-medium text-gray-800 mb-2 {{ $required ? 'required' : '' }}">{{ $label }}</label>
                                    <p class="text-xs text-gray-500 mb-3 min-h-[32px]">{{ $help }}</p>
                                    <input type="file" name="{{ $name }}" accept="{{ $accept }}" {{ $required ? 'required multiple' : '' }} class="w-full text-sm text-gray-500 file-upload">
                                    <div class="file-name mt-2 text-xs font-medium text-registration-primary truncate"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section id="step-3" class="step-container">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $setting('registration_plan_title', 'Subscription & Billing') }}</h2>
                        <p class="text-gray-500 text-sm mt-1">{{ $setting('registration_plan_intro', 'Choose the SaaS plan that fits your courier volume.') }}</p>
                    </div>
                    <div class="relative w-full mb-8 group">
                        <button type="button" id="scroll-left" class="absolute left-0 top-1/2 -translate-y-1/2 -ml-4 z-20 bg-white border border-gray-200 shadow-lg rounded-full p-2 text-gray-500 hover:text-registration-primary hidden sm:flex opacity-0 group-hover:opacity-100 transition-all" aria-label="Previous plans">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                        </button>
                        <div id="pricing-plans" class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-6 pt-2 hide-scrollbar scroll-smooth px-1">
                            @forelse($plans as $index => $plan)
                                @php
                                    $features = collect(data_get($plan->features, 'features', []))->take(3);
                                    $selected = (string) old('plan_id', optional($plans->first())->id) === (string) $plan->id;
                                @endphp
                                <div class="plan-card-wrapper snap-center relative">
                                    @if($index === 1)
                                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-registration-primary text-white text-[10px] font-bold uppercase tracking-wider py-1 px-3 rounded-full shadow-sm z-10 whitespace-nowrap">Most Popular</span>
                                    @endif
                                    <label class="relative cursor-pointer h-full block">
                                        <input type="radio" name="plan_id" value="{{ $plan->id }}" class="sr-only plan-radio" data-plan-name="{{ $plan->name }}" data-monthly="{{ $plan->monthly_price }}" data-yearly="{{ $plan->yearly_price ?: $plan->monthly_price * 12 }}" @checked($selected) required>
                                        <div class="rounded-xl border border-gray-200 bg-white p-6 hover:border-registration-primary transition-all h-full flex flex-col shadow-sm">
                                            <div class="mb-4 pb-4 border-b border-gray-100 flex-grow">
                                                <h3 class="text-lg font-bold text-gray-900 leading-tight">{{ $plan->name }}</h3>
                                                <p class="mt-1 text-xs text-gray-500 min-h-[32px]">{{ $plan->description ?: 'Courier platform subscription plan.' }}</p>
                                                <div class="mt-4 flex items-baseline">
                                                    <span class="text-3xl font-extrabold text-gray-900 tracking-tight">JMD {{ number_format($plan->monthly_price, 0) }}</span>
                                                    <span class="text-sm text-gray-500 ml-1 font-medium">/ month</span>
                                                </div>
                                            </div>
                                            <ul class="space-y-3">
                                                @forelse($features as $feature)
                                                    <li class="flex items-start">
                                                        <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" style="color: var(--registration-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                        <span class="text-sm text-gray-600">{{ $feature }}</span>
                                                    </li>
                                                @empty
                                                    <li class="text-sm text-gray-600">Users: {{ $plan->max_users ?? 'Unlimited' }}</li>
                                                    <li class="text-sm text-gray-600">Packages: {{ $plan->max_packages_month ?? 'Unlimited' }}</li>
                                                    <li class="text-sm text-gray-600">Outlets: {{ $plan->max_outlets ?? 'Unlimited' }}</li>
                                                @endforelse
                                            </ul>
                                            <svg class="check-icon absolute top-6 right-6 w-6 h-6 opacity-0 scale-50 transition-all" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                        </div>
                                    </label>
                                </div>
                            @empty
                                <div class="w-full rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">No active subscription plans are available. Please contact the system administrator.</div>
                            @endforelse
                        </div>
                        <button type="button" id="scroll-right" class="absolute right-0 top-1/2 -translate-y-1/2 -mr-4 z-20 bg-white border border-gray-200 shadow-lg rounded-full p-2 text-gray-500 hover:text-registration-primary hidden sm:flex opacity-0 group-hover:opacity-100 transition-all" aria-label="Next plans">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-gray-200 pt-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 required mb-2">Preferred Billing Method</label>
                            <select name="billing_method" required class="registration-input block w-full px-3 py-2.5 border border-gray-300 sm:text-sm rounded-lg shadow-sm">
                                <option value="" disabled @selected(!old('billing_method'))>Select method</option>
                                @foreach(['Credit Card', 'Bank Transfer (Wire/ACH)', 'PayPal'] as $method)
                                    <option value="{{ $method }}" @selected(old('billing_method') === $method)>{{ $method }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-2">Final gateway options are shown on the secure payment page.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 required mb-2">Billing Cycle</label>
                            <select id="billing_cycle" name="billing_cycle" required class="registration-input block w-full px-3 py-2.5 border border-gray-300 sm:text-sm rounded-lg shadow-sm">
                                <option value="monthly" @selected(old('billing_cycle', 'monthly') === 'monthly')>Monthly</option>
                                <option value="yearly" @selected(old('billing_cycle') === 'yearly')>Yearly</option>
                            </select>
                        </div>
                        <label class="registration-soft md:col-span-2 p-5 rounded-xl border flex items-start cursor-pointer">
                            <input name="website_development_requested" value="1" type="checkbox" class="mt-1 h-5 w-5 rounded border-gray-300" @checked(old('website_development_requested'))>
                            <span class="ml-3">
                                <span class="text-sm font-bold text-gray-900">{{ $setting('registration_website_offer_title', 'I need a courier website developed.') }}</span>
                                <span class="block text-xs text-gray-600 mt-1">{{ $setting('registration_website_offer_text', "Don't have a website for your customers to log in? We can build a custom, integrated website for your brand.") }}</span>
                            </span>
                        </label>
                    </div>
                </section>

                <section id="step-4" class="step-container">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">{{ $setting('registration_review_title', 'Review & Checkout') }}</h2>
                        <p class="text-gray-500 text-sm mt-1">{{ $setting('registration_review_intro', 'Please review your setup details before continuing to secure payment.') }}</p>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-6">
                        <h3 class="text-md font-bold text-gray-800 mb-4 border-b border-gray-200 pb-2">Onboarding Summary</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between gap-4"><span class="text-gray-500">Business Name:</span><span class="font-medium text-gray-900 text-right" id="summary-business">-</span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-500">System Prefix Code:</span><span class="font-medium text-gray-900" id="summary-code">-</span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-500">Selected Plan:</span><span class="font-medium text-registration-primary text-right" id="summary-plan">-</span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-500">Billing Method:</span><span class="font-medium text-gray-900 text-right" id="summary-billing">-</span></div>
                            <div class="flex justify-between gap-4 hidden" id="summary-website-row"><span class="text-gray-500">Website Development:</span><span class="font-medium" style="color: var(--registration-accent)">Opted In</span></div>
                        </div>
                        <div class="mt-6 border-t border-gray-200 pt-4">
                            <h4 class="text-sm font-bold text-gray-800 mb-2">Subscription Due at Checkout</h4>
                            <div class="flex justify-between text-lg font-bold text-gray-900 mt-2 pt-2 border-t border-gray-300">
                                <span>Total</span><span id="summary-total">JMD 0.00</span>
                            </div>
                        </div>
                    </div>
                    <label class="flex items-start cursor-pointer">
                        <input name="terms" value="1" required type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300" @checked(old('terms'))>
                        <span class="ml-3 text-sm font-medium text-gray-700">{{ $setting('registration_terms_text', 'I agree to the SaaS Terms of Service and Data Processing Agreement.') }}</span>
                    </label>
                </section>

                <div id="client-error" class="mt-5 text-sm text-red-600 bg-red-50 p-3 rounded-lg hidden border border-red-200">Please fill out all required fields correctly to proceed.</div>
                <div class="mt-10 pt-6 border-t border-gray-200 flex items-center justify-between">
                    <button type="button" id="btn-prev" class="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">{{ $setting('registration_back_button', 'Back') }}</button>
                    <div class="flex-1"></div>
                    <button type="button" id="btn-next" class="px-8 py-2.5 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-registration-primary transition">{{ $setting('registration_next_button', 'Next Step') }}</button>
                    <button type="submit" id="btn-submit" class="px-8 py-2.5 border border-transparent rounded-lg shadow-md text-sm font-medium text-white transition" style="background-color: var(--registration-accent)" @disabled($plans->isEmpty())>{{ $setting('registration_submit_button', 'Continue to Payment') }}</button>
                </div>
                <div class="text-center mt-6 text-sm text-gray-500">Already registered? <a href="{{ route('login') }}" class="font-semibold text-registration-primary">Sign in</a></div>
            </form>
        </main>
    </div>

    <script>
        (() => {
            const form = document.getElementById('onboardingForm');
            const totalSteps = 4;
            let currentStep = {{ $initialStep }};
            const nextButton = document.getElementById('btn-next');
            const previousButton = document.getElementById('btn-prev');
            const submitButton = document.getElementById('btn-submit');
            const errorBox = document.getElementById('client-error');
            const progress = document.getElementById('progress-bar-fill');

            const updateSummary = () => {
                const plan = form.querySelector('input[name="plan_id"]:checked');
                const cycle = document.getElementById('billing_cycle').value;
                const amount = plan ? Number(plan.dataset[cycle] || 0) : 0;
                document.getElementById('summary-business').textContent = form.elements.company_name.value || '-';
                document.getElementById('summary-code').textContent = (form.elements.company_code.value || '-').toUpperCase();
                document.getElementById('summary-plan').textContent = plan ? `${plan.dataset.planName} (${cycle})` : 'Not selected';
                document.getElementById('summary-billing').textContent = form.elements.billing_method.value || 'Not selected';
                document.getElementById('summary-total').textContent = `JMD ${amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                document.getElementById('summary-website-row').classList.toggle('hidden', !form.elements.website_development_requested.checked);
            };

            const updateUI = () => {
                for (let stepNumber = 1; stepNumber <= totalSteps; stepNumber++) {
                    const step = document.getElementById(`step-${stepNumber}`);
                    const indicator = document.getElementById(`step-indicator-${stepNumber}`);
                    const label = document.getElementById(`step-label-${stepNumber}`);
                    step.classList.toggle('step-active', stepNumber === currentStep);
                    step.classList.toggle('step-hidden', stepNumber !== currentStep);
                    indicator.className = 'w-8 h-8 rounded-full flex items-center justify-center font-semibold text-sm ring-4 ring-white transition-colors duration-300';
                    if (stepNumber < currentStep) {
                        indicator.classList.add('completed-step', 'text-white');
                        indicator.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                        label.style.color = 'var(--registration-accent)';
                    } else if (stepNumber === currentStep) {
                        indicator.classList.add('bg-registration-primary', 'text-white');
                        indicator.textContent = stepNumber;
                        label.style.color = 'var(--registration-primary)';
                    } else {
                        indicator.classList.add('bg-gray-200', 'text-gray-500');
                        indicator.textContent = stepNumber;
                        label.style.color = '#6b7280';
                    }
                }
                progress.style.width = `${((currentStep - 1) / (totalSteps - 1)) * 100}%`;
                previousButton.classList.toggle('hidden', currentStep === 1);
                nextButton.classList.toggle('hidden', currentStep === totalSteps);
                submitButton.classList.toggle('hidden', currentStep !== totalSteps);
                errorBox.classList.add('hidden');
                if (currentStep === totalSteps) updateSummary();
            };

            const validateStep = () => {
                const current = document.getElementById(`step-${currentStep}`);
                const fields = current.querySelectorAll('input, select, textarea');
                for (const field of fields) {
                    if (!field.checkValidity()) {
                        errorBox.classList.remove('hidden');
                        field.reportValidity();
                        return false;
                    }
                }
                if (currentStep === 1 && form.elements.password.value !== form.elements.password_confirmation.value) {
                    form.elements.password_confirmation.setCustomValidity('Passwords do not match.');
                    form.elements.password_confirmation.reportValidity();
                    form.elements.password_confirmation.setCustomValidity('');
                    return false;
                }
                return true;
            };

            nextButton.addEventListener('click', () => {
                if (!validateStep()) return;
                currentStep++;
                updateUI();
                window.scrollTo({top: 0, behavior: 'smooth'});
            });
            previousButton.addEventListener('click', () => {
                currentStep--;
                updateUI();
                window.scrollTo({top: 0, behavior: 'smooth'});
            });
            form.addEventListener('submit', event => {
                if (!validateStep()) event.preventDefault();
            });
            document.getElementById('same_address').addEventListener('change', event => {
                const billing = document.getElementById('billing_address');
                billing.value = event.target.checked ? document.getElementById('business_address').value : '';
                billing.readOnly = event.target.checked;
                billing.classList.toggle('bg-gray-100', event.target.checked);
            });
            document.getElementById('business_address').addEventListener('input', event => {
                if (document.getElementById('same_address').checked) document.getElementById('billing_address').value = event.target.value;
            });
            document.querySelectorAll('.file-upload').forEach(input => {
                input.addEventListener('change', () => {
                    const label = input.parentElement.querySelector('.file-name');
                    label.textContent = input.files.length === 1 ? `Attached: ${input.files[0].name}` : (input.files.length ? `Attached: ${input.files.length} files` : '');
                });
            });
            document.getElementById('scroll-left').addEventListener('click', () => document.getElementById('pricing-plans').scrollBy({left: -320, behavior: 'smooth'}));
            document.getElementById('scroll-right').addEventListener('click', () => document.getElementById('pricing-plans').scrollBy({left: 320, behavior: 'smooth'}));
            form.querySelectorAll('input[name="plan_id"], #billing_cycle, [name="billing_method"], [name="website_development_requested"]').forEach(field => field.addEventListener('change', updateSummary));
            updateUI();
            document.documentElement.classList.remove('registration-loading');
        })();
    </script>
</body>
</html>
