import { BookOpen, Plus, Search } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/components/common/PageHeader'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Combobox } from '@/components/ui/Combobox'
import { EmptyState } from '@/components/ui/EmptyState'
import { FilterField, FilterToolbar } from '@/components/ui/FilterToolbar'
import { Input } from '@/components/ui/Input'
import { Pagination } from '@/components/ui/Pagination'
import { KnowledgeArticleForm } from '@/features/knowledge/KnowledgeArticleForm'
import { useKnowledgeArticles } from '@/features/knowledge/useKnowledge'
import { useAuth } from '@/hooks/useAuth'
import { useDebouncedSearch } from '@/hooks/useDebouncedValue'
import { paths } from '@/routes/paths'
import { ApiError } from '@/types/api'
import { formatDate } from '@/utils/format'

export function KnowledgeBasePage() {
  const navigate = useNavigate()
  const { hasPermission } = useAuth()
  const canManage = hasPermission('knowledge.manage')
  const [params, setParams] = useSearchParams()
  const [formOpen, setFormOpen] = useState(false)

  const filters = useMemo(
    () => ({
      search: params.get('search') ?? '',
      topic: params.get('topic') ?? '',
      published: (params.get('published') ?? '') as '' | '1' | '0',
      page: Number(params.get('page') ?? '1') || 1,
      per_page: 12,
    }),
    [params],
  )

  const query = useKnowledgeArticles(filters)
  const [searchDraft, setSearchDraft] = useDebouncedSearch(filters.search, (value) =>
    updateFilter('search', value),
  )

  const topics = useMemo(() => {
    const values = new Set<string>()
    for (const article of query.data?.data ?? []) {
      if (article.topic) values.add(article.topic)
    }
    if (filters.topic) values.add(filters.topic)
    return [...values].sort((left, right) => left.localeCompare(right))
  }, [filters.topic, query.data?.data])

  function updateFilter(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const errorMessage = query.isError
    ? query.error instanceof ApiError
      ? query.error.message
      : 'Unable to load articles.'
    : undefined

  return (
    <div className="space-y-6">
      <PageHeader
        title="Knowledge base"
        description="Guides and answers published by IT support."
        actions={
          canManage ? (
            <Button leadingIcon={<Plus className="size-4" />} onClick={() => setFormOpen(true)}>
              New article
            </Button>
          ) : undefined
        }
      />

      <Card>
        <FilterToolbar className={canManage ? 'xl:grid-cols-3' : 'xl:grid-cols-2'}>
          <FilterField label="Search">
            {(id) => (
              <Input
                id={id}
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder="Title, summary or topic"
                leadingIcon={<Search className="size-4" />}
              />
            )}
          </FilterField>
          <FilterField label="Topic">
            {(id) => (
              <Combobox
                id={id}
                value={filters.topic}
                onChange={(value) => updateFilter('topic', value)}
                placeholder="All topics"
                options={topics.map((topic) => ({ value: topic, label: topic }))}
              />
            )}
          </FilterField>
          {canManage && (
            <FilterField label="Visibility">
              {(id) => (
                <Combobox
                  id={id}
                  value={filters.published}
                  onChange={(value) => updateFilter('published', value)}
                  placeholder="All articles"
                  options={[
                    { value: '1', label: 'Published' },
                    { value: '0', label: 'Drafts' },
                  ]}
                />
              )}
            </FilterField>
          )}
        </FilterToolbar>

        {errorMessage ? (
          <EmptyState title="Unable to load articles" description={errorMessage} />
        ) : !query.isPending && (query.data?.data.length ?? 0) === 0 ? (
          <EmptyState
            icon={BookOpen}
            title="No articles match these filters"
            description={canManage ? 'Publish a guide so employees can find answers here.' : 'IT has not published a matching guide yet.'}
          />
        ) : (
          <div className="grid gap-4 p-4 sm:grid-cols-2 sm:p-6 xl:grid-cols-3">
            {(query.data?.data ?? []).map((article) => (
              <button
                key={article.id}
                type="button"
                onClick={() => void navigate(paths.knowledgeArticle(article.id))}
                className="rounded-xl border border-surface-200 bg-panel p-4 text-left shadow-sm transition-colors hover:border-primary-200"
              >
                <div className="flex items-start justify-between gap-3">
                  <p className="text-sm font-semibold text-surface-900">{article.title}</p>
                  {!article.is_published && (
                    <span className="rounded-full bg-warning-100 px-2 py-0.5 text-[11px] font-medium text-warning-700">
                      Draft
                    </span>
                  )}
                </div>
                <p className="mt-2 line-clamp-3 text-sm text-surface-500">
                  {article.summary || 'Open to read the full guide.'}
                </p>
                <p className="mt-3 text-xs text-surface-400">
                  {article.topic ? `${article.topic} · ` : ''}
                  Updated {formatDate(article.updated_at)}
                </p>
              </button>
            ))}
          </div>
        )}

        {query.data?.meta && (
          <Pagination
            meta={query.data.meta}
            onPageChange={(page) => updateFilter('page', String(page))}
          />
        )}
      </Card>

      <KnowledgeArticleForm isOpen={formOpen} onClose={() => setFormOpen(false)} />
    </div>
  )
}
