import { AppProviders } from './app/providers/AppProviders';
import { AppRouter } from './app/router/index.tsx';

export default function App() {
  return (
    <AppProviders>
      <AppRouter />
    </AppProviders>
  );
}
