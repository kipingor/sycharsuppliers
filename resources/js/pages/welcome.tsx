import AppLogoIcon from '@/components/app-logo-icon';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Droplets } from 'lucide-react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome to Sychar Suppliers Water Billing">
            </Head>
            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-slate-100 dark:from-slate-900 dark:to-slate-800">
                {/* Header */}
                <header className="border-b border-blue-200 dark:border-slate-700 bg-white dark:bg-slate-800">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <AppLogoIcon className="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                            <span className="text-xl font-bold text-slate-900 dark:text-white">Sychar Suppliers</span>
                        </div>
                        <nav className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="inline-block px-6 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={route('login')}
                                    className="inline-block px-6 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors"
                                >
                                    Log In
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero Section */}
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                    <div className="grid md:grid-cols-2 gap-12 items-center">
                        {/* Left Side - Content */}
                        <div className="space-y-8">
                            <div className="space-y-4">
                                <div className="flex items-center gap-2 fill-current text-[var(--foreground)] dark:text-blue-400">
                                    <AppLogoIcon className="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                                    <span className="font-semibold">Water Management</span>
                                </div>
                                <h1 className="text-4xl md:text-5xl font-bold text-slate-900 dark:text-white leading-tight">
                                    Smart Water Billing Made Simple
                                </h1>
                                <p className="text-lg text-slate-600 dark:text-slate-300">
                                    Manage water accounts, track meter readings, generate bills, and process payments with our comprehensive billing management system.
                                </p>
                            </div>

                            {/* Features */}
                            <div className="space-y-4">
                                <h2 className="text-xl font-semibold text-slate-900 dark:text-white">Key Features</h2>
                                <ul className="space-y-3">
                                    <li className="flex items-start gap-3">
                                        <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold mt-1">✓</span>
                                        <span className="text-slate-700 dark:text-slate-300">Account and Meter Management</span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold mt-1">✓</span>
                                        <span className="text-slate-700 dark:text-slate-300">Automated Billing Generation</span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold mt-1">✓</span>
                                        <span className="text-slate-700 dark:text-slate-300">Payment Processing and Tracking</span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold mt-1">✓</span>
                                        <span className="text-slate-700 dark:text-slate-300">Comprehensive Billing Reports</span>
                                    </li>
                                    <li className="flex items-start gap-3">
                                        <span className="flex-shrink-0 w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold mt-1">✓</span>
                                        <span className="text-slate-700 dark:text-slate-300">Balance Tracking and History</span>
                                    </li>
                                </ul>
                            </div>

                            {/* CTA */}
                            {!auth.user && (
                                <div className="pt-4">
                                    <Link
                                        href={route('login')}
                                        className="inline-block px-8 py-3 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-semibold transition-colors"
                                    >
                                        Get Started
                                    </Link>
                                </div>
                            )}
                        </div>

                        {/* Right Side - Illustration */}
                        <div className="hidden md:flex items-center justify-center">
                            <div className="relative w-full max-w-md">
                                <div className="absolute inset-0 bg-gradient-to-r from-blue-400 to-sky-400 rounded-3xl opacity-20 blur-3xl"></div>
                                <div className="relative bg-gradient-to-br from-blue-500 to-sky-600 rounded-3xl p-12 shadow-2xl">
                                    <div className="space-y-6 text-white">
                                        <div className="space-y-2">
                                            <p className="text-sm opacity-90">Total Accounts</p>
                                            <p className="text-4xl font-bold">1,248</p>
                                        </div>
                                        <div className="space-y-2 pt-6 border-t border-white/20">
                                            <p className="text-sm opacity-90">Monthly Revenue</p>
                                            <p className="text-4xl font-bold">KES 2.3M</p>
                                        </div>
                                        <div className="space-y-2 pt-6 border-t border-white/20">
                                            <p className="text-sm opacity-90">Pending Payments</p>
                                            <p className="text-4xl font-bold">KES 584K</p>
                                        </div>
                                        <div className="flex items-center gap-2 pt-6 border-t border-white/20">
                                            <Droplets className="w-5 h-5" />
                                            <span className="text-sm">Sychar Suppliers</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <footer className="border-t border-blue-200 dark:border-slate-700 bg-white dark:bg-slate-800 mt-20">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        <div className="grid md:grid-cols-3 gap-8">
                            <div>
                                <h3 className="font-semibold text-slate-900 dark:text-white mb-3">About</h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Sychar Suppliers provides comprehensive water billing and account management solutions.
                                </p>
                            </div>
                            <div>
                                <h3 className="font-semibold text-slate-900 dark:text-white mb-3">Contact</h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    Phone: (+254) 0714 594 646<br />
                                    Email: sales@sycharsuppliers.com
                                </p>
                            </div>
                            <div>
                                <h3 className="font-semibold text-slate-900 dark:text-white mb-3">Bank Details</h3>
                                <p className="text-sm text-slate-600 dark:text-slate-400">
                                    NCBA Bank<br />
                                    Account: 1001821276<br />
                                    PayBill: 880100
                                </p>
                            </div>
                        </div>
                        <div className="border-t border-slate-200 dark:border-slate-700 mt-8 pt-8 text-center text-sm text-slate-600 dark:text-slate-400">
                            <p>&copy; 2025 Sychar Suppliers. All rights reserved.</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
