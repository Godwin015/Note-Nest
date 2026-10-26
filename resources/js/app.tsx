import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import '@mantine/core/styles.css';
import './index.css';
import { MantineProvider } from '@mantine/core';
import NoteNestApp from './NoteNestApp';
import { Notifications } from '@mantine/notifications';
import '@mantine/notifications/styles.css';

const rootElement = document.getElementById('root');

if (rootElement) {
    createRoot(rootElement).render(
        <StrictMode>
            <MantineProvider
                theme={{
                    primaryColor: 'indigo',
                    defaultRadius: 'md',
                    fontFamily: 'Inter, sans-serif',
                    headings: {
                        fontFamily: 'Inter, sans-serif',
                    },
                }}
            >
                <Notifications />
                <NoteNestApp />
            </MantineProvider>
        </StrictMode>,
    );
}
