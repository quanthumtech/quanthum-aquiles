import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Register() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/register');
    }

    return (
        <>
            <Head title="Cadastro" />
            <main className="flex min-h-svh items-center justify-center px-4">
                <div className="w-full max-w-sm space-y-6">
                    <h1 className="text-center text-2xl font-semibold tracking-tight">Criar conta</h1>

                    <Card>
                        <CardContent className="pt-6">
                            <form onSubmit={submit} className="flex flex-col gap-4">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="name">Nome</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        autoFocus
                                        required
                                    />
                                    {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
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

                                <div className="grid gap-1.5">
                                    <Label htmlFor="password_confirmation">Confirme a senha</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        required
                                    />
                                </div>

                                <Button type="submit" disabled={processing} className="w-full">
                                    Criar conta
                                </Button>

                                <p className="text-muted-foreground text-center text-sm">
                                    Já tem conta?{' '}
                                    <Link href="/login" className="text-foreground hover:underline">
                                        Entrar
                                    </Link>
                                </p>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
