import { AppProviders } from './providers/AppProviders';
import { AppRouter } from './router/index.tsx';

export default function App() {
  return (
    <AppProviders>
      <AppRouter />
    </AppProviders>
  );
}
