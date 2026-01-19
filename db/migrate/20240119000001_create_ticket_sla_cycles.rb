# frozen_string_literal: true

class CreateTicketSlaCycles < ActiveRecord::Migration[7.0]
  def change
    create_table :ticket_sla_cycles do |t|
      t.references :ticket, null: false, foreign_key: true
      t.bigint :sla_profile_id
      t.datetime :started_at, null: false
      t.datetime :paused_at
      t.string :status, null: false
      t.string :reason, null: false
      t.integer :elapsed_seconds, null: false, default: 0

      t.timestamps
    end
  end
end
