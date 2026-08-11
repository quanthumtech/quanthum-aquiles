import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/reset-password');
    }

    return (
        <>
            <Head title="Nova senha" />
            <main className="flex min-h-svh items-center justify-center px-4">
                <div className="w-full max-w-sm space-y-6">
                    <h1 className="text-center text-2xl font-semibold tracking-tight">Defina uma nova senha</h1>

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
                                    <Label htmlFor="password">Nova senha</Label>
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
                                    <Label htmlFor="password_confirmation">Confirme a nova senha</Label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        required
                                    />
                                </div>

                                <Button type="submit" disabled={processing} className="w-full">
                                    Trocar senha
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
