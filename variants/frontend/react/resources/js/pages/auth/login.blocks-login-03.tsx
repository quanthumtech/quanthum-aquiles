import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { GalleryVerticalEnd } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

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
            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10">
                <div className="flex w-full max-w-sm flex-col gap-6">
                    <Link href="/" className="flex items-center gap-2 self-center font-medium">
                        <div className="flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <GalleryVerticalEnd className="size-4" />
                        </div>
                        Quanthum Architecture
                    </Link>

                    <Card>
                        <CardHeader className="text-center">
                            <CardTitle className="text-xl">Bem-vindo de volta</CardTitle>
                            <CardDescription>Entre com seu e-mail e senha</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {status && <p className="mb-4 text-center text-sm text-green-600">{status}</p>}

                            <form onSubmit={submit}>
                                <FieldGroup>
                                    <Field>
                                        <FieldLabel htmlFor="email">E-mail</FieldLabel>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            autoFocus
                                            required
                                        />
                                        {errors.email && <p className="text-destructive text-xs">{errors.email}</p>}
                                    </Field>

                                    <Field>
                                        <div className="flex items-center">
                                            <FieldLabel htmlFor="password">Senha</FieldLabel>
                                            {canResetPassword && (
                                                <Link
                                                    href="/forgot-password"
                                                    className="ml-auto text-sm underline-offset-4 hover:underline"
                                                >
                                                    Esqueceu a senha?
                                                </Link>
                                            )}
                                        </div>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            required
                                        />
                                        {errors.password && <p className="text-destructive text-xs">{errors.password}</p>}
                                    </Field>

                                    <Field>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={data.remember}
                                                onChange={(e) => setData('remember', e.target.checked)}
                                            />
                                            Lembrar de mim
                                        </label>
                                    </Field>

                                    <Field>
                                        <Button type="submit" disabled={processing}>
                                            Entrar
                                        </Button>
                                        {canRegister && (
                                            <FieldDescription className="text-center">
                                                Não tem conta?{' '}
                                                <Link href="/register" className="text-foreground hover:underline">
                                                    Cadastre-se
                                                </Link>
                                            </FieldDescription>
                                        )}
                                    </Field>
                                </FieldGroup>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
