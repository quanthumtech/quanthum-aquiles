import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}

export default function Login({ status, canResetPassword, canRegister }: LoginProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/login');
    }

    return (
        <>
            <Head title="Entrar" />
            <main className="flex min-h-svh items-center justify-center px-4">
                <div className="w-full max-w-sm space-y-6">
                    <h1 className="text-center text-2xl font-semibold tracking-tight">Entrar</h1>

                    {status && <p className="text-center text-sm text-green-600">{status}</p>}

                    <Card>
                        <CardContent className="pt-6">
                            <form onSubmit={submit} className="flex flex-col gap-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        autoFocus
                                        required
                                    />
                                    {errors.email && <p className="text-destructive text-xs">{errors.email}</p>}
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="password">Senha</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        required
                                    />
                                    {errors.password && <p className="text-destructive text-xs">{errors.password}</p>}
                                </div>

                                <div className="flex items-center justify-between text-sm">
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            checked={data.remember}
                                            onChange={(e) => setData('remember', e.target.checked)}
                                        />
                                        Lembrar
                                    </label>

                                    {canResetPassword && (
                                        <Link href="/forgot-password" className="text-muted-foreground hover:underline">
                                            Esqueceu a senha?
                                        </Link>
                                    )}
                                </div>

                                <Button type="submit" disabled={processing} className="w-full">
                                    Entrar
                                </Button>

                                {canRegister && (
                                    <p className="text-muted-foreground text-center text-sm">
                                        Não tem conta?{' '}
                                        <Link href="/register" className="text-foreground hover:underline">
                                            Cadastre-se
                                        </Link>
                                    </p>
                                )}
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
