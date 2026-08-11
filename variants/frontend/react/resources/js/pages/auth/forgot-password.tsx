import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ForgotPasswordProps {
    status?: string;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/forgot-password');
    }

    return (
        <>
            <Head title="Recuperar senha" />
            <main className="flex min-h-svh items-center justify-center px-4">
                <div className="w-full max-w-sm space-y-6">
                    <div className="space-y-1 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">Recuperar senha</h1>
                        <p className="text-muted-foreground text-sm">Esqueceu sua senha? Sem problema.</p>
                    </div>

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

                                <Button type="submit" disabled={processing} className="w-full">
                                    Enviar link de recuperação
                                </Button>

                                <Link href="/login" className="text-muted-foreground text-center text-sm hover:underline">
                                    Voltar pro login
                                </Link>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </>
    );
}
