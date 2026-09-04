import { Navigate, Route, BrowserRouter as Router, Routes } from 'react-router-dom'
import { Nav } from './components/Nav'
import { AuthProvider, useAuth } from './context/AuthContext'
import { BooksPage } from './pages/BooksPage'
import { NewBookPage } from './pages/NewBookPage'
import { BookDetailPage } from './pages/BookDetailPage'
import { DeclareTrouvaillePage } from './pages/DeclareTrouvaillePage'
import { LoginPage } from './pages/LoginPage'
import { RegisterPage } from './pages/RegisterPage'
import './App.css'

function RequireAuth({ children }) {
  const { isAuthenticated } = useAuth()
  return isAuthenticated ? children : <Navigate to="/connexion" replace />
}

function AppRoutes() {
  return (
    <>
      <Nav />
      <main>
        <Routes>
          <Route path="/" element={<BooksPage />} />
          <Route path="/connexion" element={<LoginPage />} />
          <Route path="/inscription" element={<RegisterPage />} />
          <Route path="/livres/:id" element={<BookDetailPage />} />
          <Route
            path="/livres/nouveau"
            element={
              <RequireAuth>
                <NewBookPage />
              </RequireAuth>
            }
          />
          <Route
            path="/trouvaille"
            element={
              <RequireAuth>
                <DeclareTrouvaillePage />
              </RequireAuth>
            }
          />
        </Routes>
      </main>
    </>
  )
}

function App() {
  return (
    <AuthProvider>
      <Router>
        <AppRoutes />
      </Router>
    </AuthProvider>
  )
}

export default App
