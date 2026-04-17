import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './app/App.tsx'
import 'antd/dist/reset.css'
import './index.css'
import './styles/design-bundle-overrides.css'
import './styles/tokens.css'
import './styles/ui-overrides.css'

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
