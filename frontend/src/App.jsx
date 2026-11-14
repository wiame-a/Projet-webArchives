import { Route, Routes } from 'react-router-dom'
import Layout from './components/Layout'
import ProtectedRoute from './components/ProtectedRoute'
import DocumentsBrowser from './components/DocumentsBrowser'
import Categories from './pages/Categories'
import Dashboard from './pages/Dashboard'
import DocumentDetail from './pages/DocumentDetail'
import DocumentEdit from './pages/DocumentEdit'
import DocumentNew from './pages/DocumentNew'
import Activity from './pages/Activity'
import Login from './pages/Login'
import NotFound from './pages/NotFound'
import Profile from './pages/Profile'
import Register from './pages/Register'
import Users from './pages/Users'

export default function App() {
  return (
    <Routes>
      {/* Pages publiques */}
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />

      {/* Pages réservées aux utilisateurs connectés */}
      <Route element={<ProtectedRoute />}>
        <Route element={<Layout />}>
          <Route index element={<Dashboard />} />
          <Route path="documents" element={<DocumentsBrowser mode="list" />} />
          <Route path="documents/new" element={<DocumentNew />} />
          <Route path="documents/:id" element={<DocumentDetail />} />
          <Route path="documents/:id/edit" element={<DocumentEdit />} />
          <Route path="search" element={<DocumentsBrowser mode="search" />} />
          <Route path="archives" element={<DocumentsBrowser mode="archives" />} />
          <Route path="profile" element={<Profile />} />
        </Route>
      </Route>

      {/* Journal d'activité : administrateurs et archivistes */}
      <Route element={<ProtectedRoute roles={['admin', 'archiviste']} />}>
        <Route element={<Layout />}>
          <Route path="activity" element={<Activity />} />
        </Route>
      </Route>

      {/* Pages réservées aux administrateurs */}
      <Route element={<ProtectedRoute roles={['admin']} />}>
        <Route element={<Layout />}>
          <Route path="categories" element={<Categories />} />
          <Route path="users" element={<Users />} />
        </Route>
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}
