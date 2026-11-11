import { Pencil } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card, CardContent } from '@/components/ui/Card'
import { ErrorState } from '@/components/ui/ErrorState'
import { LoadingPanel } from '@/components/ui/Spinner'
import { KnowledgeArticleForm } from '@/features/knowledge/KnowledgeArticleForm'
import { useKnowledgeArticle } from '@/features/knowledge/useKnowledge'
import { useAuth } from '@/hooks/useAuth'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { formatDateTime } from '@/utils/format'

export function KnowledgeArticlePage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { hasPermission } = useAuth()
  const canManage = hasPermission('knowledge.manage')
  const query = useKnowledgeArticle(id)
  const [editing, setEditing] = useState(false)

  if (query.isPending) {
    return <LoadingPanel label="Loading article…" />
  }

  if (query.isError || !query.data) {
    return (
      <ErrorState
        title="Article not found"
        description={query.error instanceof ApiError ? query.error.message : 'This guide is unavailable.'}
        onRetry={() => void navigate(paths.knowledgeBase)}
      />
    )
  }

  const article = query.data

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow={
          <Link to={paths.knowledgeBase} className="text-sm font-medium text-primary-600 hover:text-primary-700">
            ← Knowledge base
          </Link>
        }
        title={article.title}
        description={article.summary ?? undefined}
        actions={
          canManage ? (
            <Button variant="outline" leadingIcon={<Pencil className="size-4" />} onClick={() => setEditing(true)}>
              Edit
            </Button>
          ) : undefined
        }
      />

      <Card>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-2 text-xs text-surface-500">
            {article.topic && <Badge tone="info">{article.topic}</Badge>}
            {!article.is_published && <Badge tone="warning">Draft</Badge>}
            <span>Updated {formatDateTime(article.updated_at)}</span>
            {article.author?.full_name && <span>by {article.author.full_name}</span>}
          </div>
          <div className="prose prose-sm max-w-none whitespace-pre-wrap text-surface-800">{article.body}</div>
        </CardContent>
      </Card>

      <KnowledgeArticleForm isOpen={editing} onClose={() => setEditing(false)} article={article} />
    </div>
  )
}
