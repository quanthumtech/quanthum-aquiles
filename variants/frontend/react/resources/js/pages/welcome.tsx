import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Pillar {
    label: string;
    status: 'core' | 'flag';
}

interface WelcomeProps {
    appName: string;
    pillars: Pillar[];
}

export default function Welcome({ appName, pillars }: WelcomeProps) {
    return (
        <>
            <Head title="Bem-vindo" />
            <main className="mx-auto flex min-h-svh max-w-3xl flex-col justify-center gap-8 px-6 py-12">
                <div className="space-y-2">
                    <p className="text-muted-foreground text-sm font-medium tracking-wide uppercase">Quanthum Architecture</p>
                    <h1 className="text-3xl font-semibold tracking-tight">{appName}</h1>
                    <p className="text-muted-foreground">Núcleo Aquiles com o frontend React + Inertia + shadcn/ui.</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Pilares cobertos por este núcleo</CardTitle>
                        <CardDescription>Vêm por padrão, independente do frontend escolhido.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                            {pillars.map((pillar) => (
                                <li key={pillar.label} className="border-border flex items-center justify-between gap-2 rounded-md border px-3 py-2">
                                    <span>{pillar.label}</span>
                                    {pillar.status === 'flag' && <span className="text-muted-foreground text-xs">*</span>}
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <div className="flex gap-3">
                    <Button asChild>
                        <a href="https://laravel.com/docs" target="_blank" rel="noopener noreferrer">
                            Documentação Laravel
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href="/horizon" target="_blank" rel="noopener noreferrer">
                            Dashboard do Horizon
                        </a>
                    </Button>
                </div>
            </main>
        </>
    );
}
