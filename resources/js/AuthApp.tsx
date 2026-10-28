import { useEffect, useState, type FormEvent } from 'react';
import { apiFetch, initializeCsrf } from './api';
import NoteNestApp from './NoteNestApp';

type User = {
    id: number;
    name: string;
    email: string;
};

type AuthMode = 'login' | 'register';

function AuthApp() {
    const [user, setUser] = useState<User | null>(null);
    const [mode, setMode] = useState<AuthMode>('login');
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [validationErrors, setValidationErrors] = useState<
        Record<string, string[]>
    >({});

    useEffect(() => {
        let cancelled = false;

        async function loadUser() {
            try {
                const response = await apiFetch('/user');

                if (!response.ok) {
                    if (response.status !== 401) {
                        throw new Error('Unable to load your account.');
                    }

                    return;
                }

                const data = await response.json();

                if (!cancelled) {
                    setUser(data.user);
                }
            } catch {
                if (!cancelled) {
                    setError('Unable to connect to NoteNest. Please try again.');
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        void loadUser();

        return () => {
            cancelled = true;
        };
    }, []);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        setError('');
        setValidationErrors({});
        setSubmitting(true);

        try {
            await initializeCsrf();

            const payload =
                mode === 'register'
                    ? {
                          name,
                          email,
                          password,
                          password_confirmation: passwordConfirmation,
                      }
                    : {
                          email,
                          password,
                      };

            const response = await apiFetch(
                mode === 'register' ? '/register' : '/login',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                },
            );

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 422 && data.errors) {
                    setValidationErrors(data.errors);
                    setError(data.message ?? 'Please check your details.');
                } else {
                    setError(
                        data.message ??
                            'Authentication failed. Please try again.',
                    );
                }

                return;
            }

            setUser(data.user);
            setPassword('');
            setPasswordConfirmation('');
        } catch {
            setError('Unable to connect to NoteNest. Please try again.');
        } finally {
            setSubmitting(false);
        }
    }

    async function handleLogout() {
        setError('');

        try {
            const response = await apiFetch('/logout', {
                method: 'POST',
            });

            if (!response.ok) {
                throw new Error('Logout failed.');
            }

            setUser(null);
            setPassword('');
            setPasswordConfirmation('');
            setMode('login');
        } catch {
            setError('Unable to log out. Please try again.');
        }
    }

    if (loading) {
        return (
            <main className="auth-loading">
                <p>Loading NoteNest...</p>
            </main>
        );
    }

    if (user) {
        return (
            <>
                <div className="auth-session-bar">
                    <span>
                        Signed in as <strong>{user.name}</strong>
                    </span>

                    <button
                        type="button"
                        onClick={handleLogout}
                        className="auth-logout-button"
                    >
                        Log out
                    </button>
                </div>

                {error && (
                    <div className="auth-session-error" role="alert">
                        {error}
                    </div>
                )}

                <NoteNestApp />
            </>
        );
    }

    function fieldError(field: string) {
        const messages = validationErrors[field];

        if (!messages?.length) {
            return null;
        }

        return (
            <p className="auth-field-error" role="alert">
                {messages[0]}
            </p>
        );
    }

    return (
        <main className="auth-page">
            <section className="auth-card">
                <div className="auth-brand">
                    <div className="auth-brand-mark">N</div>
                    <h1>NoteNest</h1>
                    <p>Your thoughts, organized.</p>
                </div>

                <div className="auth-heading">
                    <h2>
                        {mode === 'login'
                            ? 'Welcome back'
                            : 'Create your account'}
                    </h2>

                    <p>
                        {mode === 'login'
                            ? 'Sign in to continue to your notes.'
                            : 'Get started with your personal workspace.'}
                    </p>
                </div>

                {error && (
                    <div className="auth-error" role="alert">
                        {error}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="auth-form">
                    {mode === 'register' && (
                        <div className="auth-field">
                            <label htmlFor="auth-name">Full name</label>

                            <input
                                id="auth-name"
                                type="text"
                                value={name}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                autoComplete="name"
                                required
                                maxLength={255}
                            />

                            {fieldError('name')}
                        </div>
                    )}

                    <div className="auth-field">
                        <label htmlFor="auth-email">Email address</label>

                        <input
                            id="auth-email"
                            type="email"
                            value={email}
                            onChange={(event) =>
                                setEmail(event.target.value)
                            }
                            autoComplete="email"
                            required
                        />

                        {fieldError('email')}
                    </div>

                    <div className="auth-field">
                        <label htmlFor="auth-password">Password</label>

                        <input
                            id="auth-password"
                            type="password"
                            value={password}
                            onChange={(event) =>
                                setPassword(event.target.value)
                            }
                            autoComplete={
                                mode === 'login'
                                    ? 'current-password'
                                    : 'new-password'
                            }
                            required
                            minLength={8}
                        />

                        {fieldError('password')}
                    </div>

                    {mode === 'register' && (
                        <div className="auth-field">
                            <label htmlFor="auth-password-confirmation">
                                Confirm password
                            </label>

                            <input
                                id="auth-password-confirmation"
                                type="password"
                                value={passwordConfirmation}
                                onChange={(event) =>
                                    setPasswordConfirmation(
                                        event.target.value,
                                    )
                                }
                                autoComplete="new-password"
                                required
                                minLength={8}
                            />
                        </div>
                    )}

                    <button
                        type="submit"
                        className="auth-submit-button"
                        disabled={submitting}
                    >
                        {submitting
                            ? 'Please wait...'
                            : mode === 'login'
                              ? 'Sign in'
                              : 'Create account'}
                    </button>
                </form>

                <div className="auth-switch">
                    {mode === 'login' ? (
                        <>
                            Don't have an account?{' '}
                            <button
                                type="button"
                                onClick={() => {
                                    setMode('register');
                                    setError('');
                                    setValidationErrors({});
                                }}
                            >
                                Create account
                            </button>
                        </>
                    ) : (
                        <>
                            Already have an account?{' '}
                            <button
                                type="button"
                                onClick={() => {
                                    setMode('login');
                                    setError('');
                                    setValidationErrors({});
                                }}
                            >
                                Sign in
                            </button>
                        </>
                    )}
                </div>
            </section>
        </main>
    );
}

export default AuthApp;
